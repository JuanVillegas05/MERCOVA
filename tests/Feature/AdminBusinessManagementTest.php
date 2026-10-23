<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveBusinessWithProduct(): Business
    {
        $category = Category::create(['name' => 'Alimentos', 'slug' => 'alimentos']);
        $plan = MembershipPlan::create([
            'name' => 'Pro', 'slug' => 'pro', 'price' => 50000, 'billing_period' => 'mensual', 'max_listings' => 50,
        ]);
        $owner = User::factory()->create(['role' => 'seller']);

        $business = Business::create([
            'user_id' => $owner->id,
            'membership_plan_id' => $plan->id,
            'name' => 'Panadería Doña Rosa',
            'slug' => 'panaderia-dona-rosa',
            'status' => 'active',
            'membership_started_at' => now(),
            'membership_expires_at' => now()->addMonth(),
        ]);

        Product::create([
            'seller_id' => $owner->id,
            'business_id' => $business->id,
            'category_id' => $category->id,
            'title' => 'Pan francés',
            'slug' => 'pan-frances-'.uniqid(),
            'description' => 'Pan recién horneado.',
            'price' => 5000,
            'commission_rate' => 0.06,
            'net_amount' => 4700,
            'transaction_type' => 'sale',
            'stock' => 20,
            'status' => 'active',
        ]);

        return $business;
    }

    public function test_non_admin_cannot_list_businesses(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/businesses')->assertStatus(403);
    }

    public function test_admin_can_list_all_businesses_regardless_of_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createActiveBusinessWithProduct();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/businesses');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('data.0.products_count'));
    }

    public function test_suspending_a_business_archives_its_active_listings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = $this->createActiveBusinessWithProduct();

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/businesses/{$business->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'suspended');

        $this->assertSame('suspended', $business->fresh()->status);
        $this->assertSame('archived', $business->products()->first()->status);
    }

    public function test_deleting_a_business_removes_it_but_keeps_products_archived_and_unlinked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $business = $this->createActiveBusinessWithProduct();
        $product = $business->products()->first();

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/businesses/{$business->id}");

        $response->assertOk();

        $this->assertNull(Business::find($business->id));

        $product->refresh();
        $this->assertSame('archived', $product->status);
        $this->assertNull($product->business_id);
    }
}
