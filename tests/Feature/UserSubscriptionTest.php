<?php

namespace Tests\Feature;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_subscribes_user_to_default_free_plan(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();

        $response = $this->postJson('/api/register', [
            'name' => 'Ana Gómez',
            'email' => 'ana@mercova.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.id', $freePlan->id)
            ->assertJsonPath('subscription.is_free', true)
            ->assertJsonPath('subscription.ends_at', null)
            ->assertJsonPath('subscription.usage.listings.remaining', 5);

        $user = User::where('email', 'ana@mercova.test')->firstOrFail();
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'membership_plan_id' => $freePlan->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_login_returns_current_subscription_status(): void
    {
        $proPlan = MembershipPlan::factory()->create();
        $user = User::factory()->create(['password' => 'secret-password']);
        Subscription::factory()->for($user)->for($proPlan, 'plan')->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.id', $proPlan->id)
            ->assertJsonPath('subscription.benefits.advanced_stats', true);
    }

    public function test_subscription_status_returns_401_without_token(): void
    {
        $this->getJson('/api/me/subscription')->assertUnauthorized();
    }

    public function test_user_without_subscription_falls_back_to_default_plan(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'default')
            ->assertJsonPath('data.plan.id', $freePlan->id);
    }

    public function test_expired_paid_subscription_falls_back_to_default_plan(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->expired()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/subscription')
            ->assertOk()
            ->assertJsonPath('data.status', 'default')
            ->assertJsonPath('data.plan.id', $freePlan->id);
    }

    public function test_paid_plan_cannot_be_activated_without_payment(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();
        $premiumPlan = MembershipPlan::factory()->premium()->create();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->for($freePlan, 'plan')->create(['ends_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/subscription', ['membership_plan_id' => $premiumPlan->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_required');

        $this->assertSame($freePlan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_returning_to_free_plan_keeps_paid_plan_until_it_expires(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();
        $paid = Subscription::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/subscription', ['membership_plan_id' => $freePlan->id])
            ->assertOk()
            ->assertJsonPath('data.plan.id', $paid->membership_plan_id)
            ->assertJsonPath('data.cancels_at_period_end', true);

        $this->assertNotNull($paid->fresh()->cancelled_at);
        $this->assertSame(Subscription::STATUS_ACTIVE, $paid->fresh()->status);

        $this->travelTo($paid->ends_at->addMinute());
        $this->assertSame($freePlan->id, $user->fresh()->currentPlan()->id);
    }

    public function test_user_cannot_subscribe_to_inactive_plan(): void
    {
        $hiddenPlan = MembershipPlan::factory()->create(['is_active' => false]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/subscription', ['membership_plan_id' => $hiddenPlan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('membership_plan_id');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_resubscribing_to_current_plan_returns_422(): void
    {
        $freePlan = MembershipPlan::factory()->free()->create();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->for($freePlan, 'plan')->create(['ends_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/me/subscription', ['membership_plan_id' => $freePlan->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ya tienes activo el plan \"{$freePlan->name}\".");

        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_admin_assigns_plan_to_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $privatePlan = MembershipPlan::factory()->premium()->create(['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$customer->id}/subscription", ['membership_plan_id' => $privatePlan->id])
            ->assertOk()
            ->assertJsonPath('data.plan.id', $privatePlan->id);

        $this->assertSame($privatePlan->id, $customer->fresh()->currentPlan()->id);
    }

    public function test_non_admin_cannot_assign_plans_to_other_users(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create();
        $premiumPlan = MembershipPlan::factory()->premium()->create();

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/admin/users/{$otherUser->id}/subscription", ['membership_plan_id' => $premiumPlan->id])
            ->assertForbidden();

        $this->assertDatabaseCount('subscriptions', 0);
    }
}
