<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Ergonómica',
            'slug' => 'silla-ergonomica-'.uniqid(),
            'description' => 'Silla de oficina con soporte lumbar.',
            'price' => 450000,
            'commission_rate' => 0.06,
            'net_amount' => 423000,
            'transaction_type' => 'sale',
            'stock' => 5,
            'status' => 'active',
        ], $overrides));
    }

    public function test_customer_can_add_product_to_cart(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.subtotal', 900000);
    }

    public function test_cannot_add_a_rental_product_to_the_cart(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, [
            'transaction_type' => 'rent',
            'price_per_day' => 50000,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_adding_more_than_available_stock_fails(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, ['stock' => 2]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(422);
    }

    public function test_customer_can_update_and_remove_cart_items(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, ['stock' => 10]);

        $add = $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $cartItemId = $add->json('data.items.0.id');

        $this->actingAs($customer, 'sanctum')->putJson("/api/cart/items/{$cartItemId}", [
            'quantity' => 4,
        ])->assertOk()->assertJsonPath('data.subtotal', 1800000);

        $this->actingAs($customer, 'sanctum')->deleteJson("/api/cart/items/{$cartItemId}")
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_another_customer_cannot_modify_someone_elses_cart_item(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $owner = User::factory()->create(['role' => 'customer']);
        $intruder = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $add = $this->actingAs($owner, 'sanctum')->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $cartItemId = $add->json('data.items.0.id');

        $this->actingAs($intruder, 'sanctum')->putJson("/api/cart/items/{$cartItemId}", [
            'quantity' => 2,
        ])->assertStatus(403);
    }

    public function test_checkout_creates_one_order_per_seller_and_empties_cart(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $sellerA = User::factory()->create(['role' => 'seller']);
        $sellerB = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $productA = $this->createProduct($sellerA, $category, ['stock' => 5, 'price' => 100000]);
        $productB = $this->createProduct($sellerB, $category, ['stock' => 5, 'price' => 50000]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productA->id, 'quantity' => 2]);
        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productB->id, 'quantity' => 1]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/checkout', [
            'recipient_name' => 'Cliente Mercova',
            'recipient_phone' => '+573001234567',
            'address' => 'Calle 1 # 2-34',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'payment_method' => 'pse',
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data'));

        $this->assertSame(3, $productA->fresh()->stock);
        $this->assertSame(4, $productB->fresh()->stock);

        $cartResponse = $this->actingAs($customer, 'sanctum')->getJson('/api/cart');
        $cartResponse->assertJsonPath('data.items', []);
    }

    public function test_checkout_with_empty_cart_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')->postJson('/api/checkout', [
            'recipient_name' => 'Cliente Mercova',
            'recipient_phone' => '+573001234567',
            'address' => 'Calle 1 # 2-34',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'payment_method' => 'pse',
        ])->assertStatus(422);
    }

    public function test_checkout_rejects_unknown_payment_method(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/checkout', [
            'recipient_name' => 'Cliente Mercova',
            'recipient_phone' => '+573001234567',
            'address' => 'Calle 1 # 2-34',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'payment_method' => 'wallet',
        ])->assertStatus(422);
    }
}
