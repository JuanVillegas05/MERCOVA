<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMembershipPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_create_a_plan(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/membership-plans', [
            'name' => 'Pro',
            'price' => 29900,
            'billing_period' => 'mensual',
        ])->assertStatus(403);
    }

    public function test_admin_can_create_a_plan_with_features(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/membership-plans', [
            'name' => 'Emprendedor Pro',
            'description' => 'Para crecer',
            'price' => 29900,
            'billing_period' => 'mensual',
            'max_listings' => 30,
            'features' => ['Hasta 30 publicaciones', 'Soporte prioritario'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'emprendedor-pro')
            ->assertJsonPath('data.max_listings', 30)
            ->assertJsonPath('data.features.1', 'Soporte prioritario');
    }

    public function test_admin_can_update_plan_price_without_touching_existing_products_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $plan = MembershipPlan::create([
            'name' => 'Básico',
            'slug' => 'basico',
            'price' => 0,
            'billing_period' => 'mensual',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/membership-plans/{$plan->id}", [
            'price' => 19900,
        ]);

        $response->assertOk()->assertJsonPath('data.price', '19900.00');
    }

    public function test_admin_index_includes_inactive_plans_but_public_index_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        MembershipPlan::create(['name' => 'Activo', 'slug' => 'activo', 'price' => 0, 'billing_period' => 'mensual', 'is_active' => true]);
        MembershipPlan::create(['name' => 'Inactivo', 'slug' => 'inactivo', 'price' => 0, 'billing_period' => 'mensual', 'is_active' => false]);

        $this->getJson('/api/membership-plans')->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/membership-plans')
            ->assertJsonCount(2, 'data');
    }

    public function test_deleting_a_plan_does_not_delete_businesses_subscribed_to_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $plan = MembershipPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 29900, 'billing_period' => 'mensual']);

        $business = Business::create([
            'user_id' => $seller->id,
            'membership_plan_id' => $plan->id,
            'name' => 'Mi negocio',
            'slug' => 'mi-negocio',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/membership-plans/{$plan->id}")
            ->assertOk();

        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
        $this->assertNull($business->fresh()->membership_plan_id);
    }
}
