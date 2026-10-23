<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCommissionTest extends TestCase
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
            'price' => 10638.30,
            'commission_rate' => 0.06,
            'net_amount' => 10000,
            'transaction_type' => 'sale',
            'stock' => 10,
            'status' => 'active',
        ], $overrides));
    }

    public function test_direct_purchase_stores_commission_breakdown_on_order_and_item(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(638.30, (float) $response->json('data.commission_amount'), 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.net_amount'), 0.01);
        $this->assertEqualsWithDelta(638.30, (float) $response->json('data.items.0.commission_amount'), 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $response->json('data.items.0.net_amount'), 0.01);
    }

    public function test_checkout_aggregates_commission_across_items_from_the_same_seller(): void
    {
        $category = Category::create(['name' => 'Tecnología', 'slug' => 'tecnologia']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $productA = $this->createProduct($seller, $category, ['price' => 100000]);
        $productB = $this->createProduct($seller, $category, ['price' => 50000]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $productA->id, 'quantity' => 1]);
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
        $order = $response->json('data.0');

        $this->assertEqualsWithDelta(150000.0, (float) $order['total'], 0.01);
        $this->assertEqualsWithDelta(9000.0, (float) $order['commission_amount'], 0.01);
        $this->assertEqualsWithDelta(141000.0, (float) $order['net_amount'], 0.01);

        $itemsCommission = array_sum(array_column($order['items'], 'commission_amount'));
        $this->assertEqualsWithDelta(9000.0, $itemsCommission, 0.01);
    }
}
