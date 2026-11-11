<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_a_business_and_becomes_seller(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/businesses', [
            'name' => 'Panadería Doña Rosa',
            'description' => 'Pan artesanal casero.',
            'category' => 'Alimentos',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame('seller', $customer->fresh()->role);
    }

    public function test_business_needs_active_membership_before_publishing_products(): void
    {
        $category = Category::create([
            'name' => 'Alimentos',
            'slug' => 'alimentos',
            'description' => 'Comida y bebidas',
        ]);

        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller, 'sanctum')->postJson('/api/businesses', [
            'name' => 'Panadería Doña Rosa',
        ])->assertStatus(201);

        $business = $seller->fresh()->business;

        $blocked = $this->actingAs($seller, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'business_id' => $business->id,
            'title' => 'Pan de masa madre',
            'description' => 'Pan artesanal recién horneado.',
            'price' => 5.5,
            'transaction_type' => 'sale',
        ]);

        $blocked->assertStatus(403);

        $plan = MembershipPlan::create([
            'name' => 'Emprendedor Pro',
            'slug' => 'pro',
            'price' => 9990,
            'billing_period' => 'mensual',
            'max_listings' => 30,
        ]);
        // El plan de pago ya fue contratado y confirmado por Mercado Pago.
        Subscription::factory()->for($seller)->create(['membership_plan_id' => $plan->id]);

        $this->actingAs($seller, 'sanctum')->postJson("/api/businesses/{$business->id}/subscribe", [
            'membership_plan_id' => $plan->id,
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $allowed = $this->actingAs($seller, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'business_id' => $business->id,
            'title' => 'Pan de masa madre',
            'description' => 'Pan artesanal recién horneado.',
            'price' => 5.5,
            'transaction_type' => 'sale',
        ]);

        $allowed->assertStatus(201)
            ->assertJsonPath('data.business_id', $business->id);
    }

    public function test_owner_cannot_activate_a_paid_plan_on_their_business_without_paying_it(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $business = $this->createPendingBusiness($seller);
        $paidPlan = MembershipPlan::factory()->create(['price' => 49900]);

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/businesses/{$business->id}/subscribe", ['membership_plan_id' => $paidPlan->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_required');

        $this->assertSame('pending', $business->fresh()->status);
        $this->assertNull($business->fresh()->membership_plan_id);
    }

    public function test_paid_membership_on_a_business_ends_when_the_owners_paid_subscription_ends(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $business = $this->createPendingBusiness($seller);
        $paidPlan = MembershipPlan::factory()->create(['price' => 49900]);
        $subscription = Subscription::factory()->for($seller)->create([
            'membership_plan_id' => $paidPlan->id,
            'ends_at' => now()->addDays(10),
        ]);

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/businesses/{$business->id}/subscribe", ['membership_plan_id' => $paidPlan->id])
            ->assertOk();

        $this->assertTrue($business->fresh()->membership_expires_at->equalTo($subscription->ends_at));
    }

    public function test_admin_can_assign_any_plan_to_a_business_without_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = $this->createPendingBusiness(User::factory()->create(['role' => 'seller']));
        $paidPlan = MembershipPlan::factory()->create(['price' => 49900]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/businesses/{$business->id}/subscribe", ['membership_plan_id' => $paidPlan->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    private function createPendingBusiness(User $owner): Business
    {
        return Business::create([
            'user_id' => $owner->id,
            'name' => 'Panadería Doña Rosa',
            'slug' => 'panaderia-dona-rosa-'.uniqid(),
            'status' => 'pending',
        ]);
    }
}
