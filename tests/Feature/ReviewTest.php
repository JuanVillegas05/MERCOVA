<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Audífonos Inalámbricos',
            'slug' => 'audifonos-'.uniqid(),
            'description' => 'Cancelación de ruido activa.',
            'price' => 249900,
            'commission_rate' => 0.06,
            'net_amount' => 234906,
            'transaction_type' => 'sale',
            'stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    public function test_customer_without_a_completed_purchase_cannot_review(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Excelente producto',
        ])->assertStatus(403);
    }

    public function test_customer_with_completed_order_can_review(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'completed',
            'total' => $product->price,
            'payment_method' => 'debit_card',
            'transaction_type' => 'sale',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
            'comment' => 'Excelente producto',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.rating', 5);
    }

    public function test_customer_with_a_pending_order_cannot_review_yet(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'total' => $product->price,
            'payment_method' => 'debit_card',
            'transaction_type' => 'sale',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
        ])->assertStatus(403);
    }

    public function test_customer_with_finished_rental_can_review(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, [
            'transaction_type' => 'rent',
            'price_per_day' => 50000,
        ]);

        Rental::create([
            'product_id' => $product->id,
            'renter_id' => $customer->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(2),
            'days' => 3,
            'price_per_day' => 50000,
            'total' => 150000,
            'commission_rate' => 0.06,
            'commission_amount' => 9000,
            'net_amount' => 141000,
            'status' => 'finished',
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
        ])->assertStatus(201);
    }

    public function test_submitting_a_second_review_updates_the_first_instead_of_duplicating(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'completed',
            'total' => $product->price,
            'payment_method' => 'debit_card',
            'transaction_type' => 'sale',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 3,
        ])->assertStatus(201);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 5,
        ])->assertStatus(201);

        $this->assertSame(1, $product->reviews()->count());
        $this->assertSame(5, $product->reviews()->first()->rating);
    }

    public function test_product_listing_exposes_average_rating_and_count(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'completed',
            'total' => $product->price,
            'payment_method' => 'debit_card',
            'transaction_type' => 'sale',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'product_id' => $product->id,
            'rating' => 4,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.data.0.reviews_count', 1)
            ->assertJsonPath('data.data.0.reviews_avg_rating', 4);
    }
}
