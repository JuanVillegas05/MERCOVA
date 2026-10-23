<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BusinessHelpersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_manage_the_business_but_a_stranger_cannot(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $admin = User::factory()->create(['role' => 'admin']);
        $stranger = User::factory()->create(['role' => 'customer']);
        $business = Business::create(['user_id' => $owner->id, 'name' => 'Mi negocio', 'slug' => 'mi-negocio', 'status' => 'pending']);

        $this->assertTrue($business->canBeManagedBy($owner));
        $this->assertTrue($business->canBeManagedBy($admin));
        $this->assertFalse($business->canBeManagedBy($stranger));
    }

    public function test_assert_can_publish_listing_blocks_without_active_membership(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $business = Business::create(['user_id' => $owner->id, 'name' => 'Mi negocio', 'slug' => 'mi-negocio', 'status' => 'pending']);

        $this->expectException(HttpException::class);
        $business->assertCanPublishListing($owner, 'productos');
    }

    public function test_assert_can_publish_listing_passes_with_active_membership_and_room_in_the_plan(): void
    {
        $owner = User::factory()->create(['role' => 'seller']);
        $plan = MembershipPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 29900, 'billing_period' => 'mensual', 'max_listings' => 5]);
        $business = Business::create([
            'user_id' => $owner->id,
            'membership_plan_id' => $plan->id,
            'name' => 'Mi negocio',
            'slug' => 'mi-negocio',
            'status' => 'active',
            'membership_started_at' => now(),
            'membership_expires_at' => now()->addMonth(),
        ]);

        $business->assertCanPublishListing($owner, 'productos');
        $this->assertTrue(true);
    }
}
