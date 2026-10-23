<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MembershipPlan;
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
}
