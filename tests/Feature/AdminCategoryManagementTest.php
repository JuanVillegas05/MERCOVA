<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_create_a_category(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Hogar',
        ])->assertStatus(403);
    }

    public function test_admin_can_create_a_top_level_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Hogar',
            'description' => 'Muebles y más',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'hogar')
            ->assertJsonPath('data.parent_id', null);
    }

    public function test_admin_can_create_a_subcategory_under_a_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Muebles',
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.parent.name', 'Hogar');
    }

    public function test_cannot_nest_a_subcategory_under_another_subcategory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $child = Category::create(['name' => 'Muebles', 'slug' => 'muebles', 'parent_id' => $parent->id]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Sillas',
            'parent_id' => $child->id,
        ])->assertStatus(422);
    }

    public function test_cannot_delete_a_category_with_products(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['role' => 'seller']);
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);

        Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla',
            'slug' => 'silla-test',
            'description' => 'Silla de oficina.',
            'price' => 100000,
            'commission_rate' => 0.06,
            'net_amount' => 94000,
            'transaction_type' => 'sale',
            'stock' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_a_category_with_subcategories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $parent = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        Category::create(['name' => 'Muebles', 'slug' => 'muebles', 'parent_id' => $parent->id]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/categories/{$parent->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_delete_an_unused_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_public_categories_endpoint_still_returns_a_plain_array(): void
    {
        Category::create(['name' => 'Hogar', 'slug' => 'hogar']);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $this->assertIsArray($response->json());
        $this->assertSame('Hogar', $response->json('0.name'));
    }
}
