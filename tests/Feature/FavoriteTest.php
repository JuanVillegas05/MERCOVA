<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, Category $category): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Bicicleta Urbana',
            'slug' => 'bicicleta-urbana-'.uniqid(),
            'description' => 'Bicicleta ideal para la ciudad.',
            'price' => 650000,
            'commission_rate' => 0.06,
            'net_amount' => 611000,
            'transaction_type' => 'sale',
            'stock' => 3,
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_add_favorite(): void
    {
        $category = Category::create(['name' => 'Deportes', 'slug' => 'deportes']);
        $seller = User::factory()->create(['role' => 'seller']);
        $product = $this->createProduct($seller, $category);

        $this->postJson('/api/favorites', ['product_id' => $product->id])->assertStatus(401);
    }

    public function test_customer_can_add_and_remove_favorite(): void
    {
        $category = Category::create(['name' => 'Deportes', 'slug' => 'deportes']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/favorites', [
            'product_id' => $product->id,
        ])->assertStatus(201);

        $list = $this->actingAs($customer, 'sanctum')->getJson('/api/favorites');
        $list->assertOk()->assertJsonPath('data.0.id', $product->id);

        $this->actingAs($customer, 'sanctum')->deleteJson("/api/favorites/{$product->id}")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')->getJson('/api/favorites')
            ->assertJsonCount(0, 'data');
    }

    public function test_adding_the_same_favorite_twice_is_idempotent(): void
    {
        $category = Category::create(['name' => 'Deportes', 'slug' => 'deportes']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/favorites', ['product_id' => $product->id])->assertStatus(201);
        $this->actingAs($customer, 'sanctum')->postJson('/api/favorites', ['product_id' => $product->id])->assertStatus(201);

        $this->actingAs($customer, 'sanctum')->getJson('/api/favorites')->assertJsonCount(1, 'data');
    }

    public function test_product_listing_marks_favorited_products_for_authenticated_user(): void
    {
        $category = Category::create(['name' => 'Deportes', 'slug' => 'deportes']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/favorites', ['product_id' => $product->id]);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/products');

        $response->assertOk()->assertJsonPath('data.data.0.is_favorited', true);
    }

    public function test_product_listing_does_not_mark_favorites_for_guests(): void
    {
        $category = Category::create(['name' => 'Deportes', 'slug' => 'deportes']);
        $seller = User::factory()->create(['role' => 'seller']);
        $this->createProduct($seller, $category);

        $response = $this->getJson('/api/products');

        $response->assertOk()->assertJsonMissingPath('data.data.0.is_favorited');
    }
}
