<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesWompi;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use FakesWompi;
    use RefreshDatabase;

    public function test_checkout_returns_wompi_link_without_changing_the_plan_yet(): void
    {
        $this->configureWompi();
        $freePlan = MembershipPlan::factory()->free()->create();
        $proPlan = MembershipPlan::factory()->create(['price' => 29900]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.change_type', SubscriptionPayment::CHANGE_NEW)
            ->assertJsonPath('data.payment.status', SubscriptionPayment::STATUS_PENDING)
            ->assertJsonPath('data.payment.amount', '29900.00')
            ->assertJsonPath('data.starts', 'on_payment');

        $parameters = $this->wompiCheckoutParameters($response->json('data.payment_url'));
        $this->assertSame('2990000', $parameters['amount-in-cents']);
        $this->assertSame($response->json('data.payment.reference'), $parameters['reference']);
        $this->assertSame(
            hash('sha256', $parameters['reference'].'2990000COP'.self::WOMPI_INTEGRITY_SECRET),
            $parameters['signature:integrity'],
        );
        $this->assertSame($freePlan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_repeated_checkout_reuses_the_pending_payment(): void
    {
        $this->configureWompi();
        $proPlan = MembershipPlan::factory()->create();
        $user = User::factory()->create();

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id]);
        $second = $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id]);

        $this->assertSame($first->json('data.payment.reference'), $second->json('data.payment.reference'));
        $this->assertDatabaseCount('subscription_payments', 1);
    }

    public function test_checkout_returns_503_when_wompi_is_not_configured(): void
    {
        $proPlan = MembershipPlan::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_checkout_returns_401_without_token(): void
    {
        $this->postJson('/api/subscriptions/checkout', ['membership_plan_id' => 1])->assertUnauthorized();
    }

    public function test_checkout_rejects_the_free_plan(): void
    {
        $this->configureWompi();
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $freePlan->id])
            ->assertUnprocessable();

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_approved_webhook_activates_the_paid_plan(): void
    {
        $this->configureWompi();
        $this->freezeTime();
        $freePlan = MembershipPlan::factory()->free()->create();
        $proPlan = MembershipPlan::factory()->create(['price' => 29900]);
        $user = User::factory()->create();
        $freeSubscription = Subscription::factory()->for($user)->for($freePlan, 'plan')->create(['ends_at' => null]);
        $payment = SubscriptionPayment::factory()->for($user)->for($proPlan, 'plan')->create(['amount' => 29900]);

        $this->sendWompiTransactionEvent($payment->reference, 'APPROVED', 29900, transactionId: '123')->assertOk();

        $payment->refresh();
        $this->assertSame(SubscriptionPayment::STATUS_APPROVED, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('123', $payment->transaction_id);
        $this->assertSame($proPlan->id, $user->fresh()->currentPlan()->id);
        $this->assertSame(now()->addMonth()->toDateTimeString(), $payment->subscription->ends_at->toDateTimeString());
        $this->assertSame(Subscription::STATUS_CANCELLED, $freeSubscription->fresh()->status);
    }

    public function test_replayed_approved_webhook_does_not_create_a_second_subscription(): void
    {
        $this->configureWompi();
        $proPlan = MembershipPlan::factory()->create(['price' => 29900]);
        $user = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($user)->for($proPlan, 'plan')->create(['amount' => 29900]);

        $this->sendWompiTransactionEvent($payment->reference, 'APPROVED', 29900)->assertOk();
        $this->sendWompiTransactionEvent($payment->reference, 'APPROVED', 29900)->assertOk();
        $this->sendWompiTransactionEvent($payment->reference, 'DECLINED', 29900)->assertOk();

        $this->assertSame(1, $user->subscriptions()->count());
        $this->assertSame(SubscriptionPayment::STATUS_APPROVED, $payment->fresh()->status);
    }

    public function test_declined_webhook_keeps_the_current_plan(): void
    {
        $this->configureWompi();
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($user)->create(['amount' => 29900]);

        $this->sendWompiTransactionEvent($payment->reference, 'DECLINED', 29900)->assertOk();

        $this->assertSame('declined', $payment->fresh()->status);
        $this->assertSame($freePlan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_approved_webhook_with_a_different_amount_does_not_activate_the_plan(): void
    {
        $this->configureWompi();
        $user = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($user)->create(['amount' => 29900]);

        $this->sendWompiTransactionEvent($payment->reference, 'APPROVED', 1)->assertOk();

        $this->assertSame(SubscriptionPayment::STATUS_AMOUNT_MISMATCH, $payment->fresh()->status);
        $this->assertSame(0, $user->subscriptions()->count());
    }

    public function test_webhook_with_invalid_signature_does_not_activate_the_plan(): void
    {
        $this->configureWompi();
        $user = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($user)->create(['amount' => 29900]);

        $this->fakeWompiTransaction('321', $payment->reference, 'APPROVED', 29900);

        $this->sendWompiEvent('321', secret: 'forged')->assertUnauthorized();

        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_upgrade_preview_discounts_the_unused_part_of_the_paid_plan(): void
    {
        $this->freezeTime();
        $proPlan = MembershipPlan::factory()->create(['tier' => 1, 'price' => 30000]);
        $premiumPlan = MembershipPlan::factory()->premium()->create(['price' => 60000]);
        $user = User::factory()->create();
        $this->paidSubscription($user, $proPlan, startedDaysAgo: 15, endsInDays: 15);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/subscriptions/checkout/preview?membership_plan_id={$premiumPlan->id}")
            ->assertOk()
            ->assertJsonPath('data.change_type', SubscriptionPayment::CHANGE_UPGRADE)
            ->assertJsonPath('data.credit_amount', 15000)
            ->assertJsonPath('data.amount', 45000)
            ->assertJsonPath('data.starts', 'on_payment');
    }

    public function test_approved_renewal_starts_when_the_current_period_ends(): void
    {
        $this->configureWompi();
        $proPlan = MembershipPlan::factory()->create(['price' => 29900]);
        $user = User::factory()->create();
        $current = $this->paidSubscription($user, $proPlan, startedDaysAgo: 20, endsInDays: 10);

        $checkout = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id])
            ->assertCreated()
            ->assertJsonPath('data.payment.change_type', SubscriptionPayment::CHANGE_RENEWAL)
            ->assertJsonPath('data.starts', 'at_period_end');

        $this->sendWompiTransactionEvent($checkout->json('data.payment.reference'), 'APPROVED', 29900)->assertOk();

        $renewal = SubscriptionPayment::where('reference', $checkout->json('data.payment.reference'))->firstOrFail()->subscription;
        $this->assertTrue($renewal->starts_at->equalTo($current->ends_at));
        $this->assertSame($current->id, $user->fresh()->activeSubscription->id);

        $this->travelTo($current->ends_at->addMinute());
        $this->assertSame($renewal->id, $user->fresh()->activeSubscription->id);
    }

    public function test_downgrade_is_scheduled_and_blocks_further_changes_until_it_starts(): void
    {
        $this->configureWompi();
        $proPlan = MembershipPlan::factory()->create(['tier' => 1, 'price' => 29900]);
        $premiumPlan = MembershipPlan::factory()->premium()->create();
        $user = User::factory()->create();
        $this->paidSubscription($user, $premiumPlan, startedDaysAgo: 5, endsInDays: 25);

        $checkout = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $proPlan->id])
            ->assertJsonPath('data.payment.change_type', SubscriptionPayment::CHANGE_DOWNGRADE);
        $this->sendWompiTransactionEvent($checkout->json('data.payment.reference'), 'APPROVED', 29900)->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/subscription')
            ->assertJsonPath('data.plan.id', $premiumPlan->id)
            ->assertJsonPath('data.scheduled.plan.id', $proPlan->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['membership_plan_id' => $premiumPlan->id])
            ->assertUnprocessable();
    }

    public function test_user_cannot_see_another_users_payment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($owner)->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/subscriptions/payments/{$payment->id}")
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/subscriptions/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.reference', $payment->reference);
    }

    public function test_checkout_returns_user_to_frontend_only_when_url_is_allowed(): void
    {
        $this->configureWompi();
        config(['mercova.frontend_url' => 'https://app.mercova.test']);
        $proPlan = MembershipPlan::factory()->create();
        $user = User::factory()->create();

        $allowed = $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/checkout', [
            'membership_plan_id' => $proPlan->id,
            'return_url' => 'https://app.mercova.test/planes/resultado',
        ]);
        $foreign = $this->actingAs($user, 'sanctum')->postJson('/api/subscriptions/checkout', [
            'membership_plan_id' => $proPlan->id,
            'return_url' => 'https://evil.test/robar',
        ]);

        $allowed->assertCreated();
        $foreign->assertCreated();
        $this->assertSame(
            'https://app.mercova.test/planes/resultado',
            $this->wompiCheckoutParameters($allowed->json('data.payment_url'))['redirect-url'],
        );
        $this->assertSame(
            route('payment.return'),
            $this->wompiCheckoutParameters($foreign->json('data.payment_url'))['redirect-url'],
        );
    }

    public function test_polling_a_pending_payment_activates_the_plan_when_the_webhook_was_lost(): void
    {
        $this->configureWompi();
        $proPlan = MembershipPlan::factory()->create(['price' => 29900]);
        $user = User::factory()->create();
        $payment = SubscriptionPayment::factory()->for($user)->for($proPlan, 'plan')->create(['amount' => 29900]);
        $this->fakeWompiTransaction('901', $payment->reference, 'APPROVED', 29900);
        $this->fakeWompiTransaction('902', $payment->reference, 'DECLINED', 29900);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/subscriptions/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionPayment::STATUS_APPROVED)
            ->assertJsonPath('data.transaction_id', '901');

        $this->assertSame($proPlan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_reconcile_command_applies_wompi_results_and_expires_abandoned_payments(): void
    {
        $this->configureWompi();
        $user = User::factory()->create();
        $declined = SubscriptionPayment::factory()->for($user)->create(['created_at' => now()->subHour()]);
        $abandoned = SubscriptionPayment::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
        $this->fakeWompiTransaction('911', $declined->reference, 'DECLINED', 29900);

        $this->artisan('subscriptions:reconcile-payments')->assertSuccessful();

        $this->assertSame('declined', $declined->fresh()->status);
        $this->assertSame(SubscriptionPayment::STATUS_EXPIRED, $abandoned->fresh()->status);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer '.self::WOMPI_PRIVATE_KEY)
            && str_contains($request->url(), 'reference='.$declined->reference));
    }

    /**
     * Suscripción paga vigente respaldada por un pago aprobado (lo que
     * habilita el crédito por prorrateo en un upgrade).
     */
    private function paidSubscription(User $user, MembershipPlan $plan, int $startedDaysAgo, int $endsInDays): Subscription
    {
        $subscription = Subscription::factory()->for($user)->for($plan, 'plan')->create([
            'starts_at' => now()->subDays($startedDaysAgo),
            'ends_at' => now()->addDays($endsInDays),
        ]);

        SubscriptionPayment::factory()->for($user)->for($plan, 'plan')->create([
            'subscription_id' => $subscription->id,
            'amount' => $plan->price,
            'status' => SubscriptionPayment::STATUS_APPROVED,
            'paid_at' => $subscription->starts_at,
        ]);

        return $subscription;
    }
}
