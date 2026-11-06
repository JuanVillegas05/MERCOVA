<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(User $seller, Category $category, int $stock = 5): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Ergonómica',
            'slug' => 'silla-ergonomica-'.uniqid(),
            'description' => 'Silla de oficina con soporte lumbar.',
            'price' => 450000,
            'commission_rate' => 0.06,
            'net_amount' => 423000,
            'transaction_type' => 'sale',
            'stock' => $stock,
            'status' => 'active',
        ]);
    }

    public function test_buyer_can_cancel_a_pending_order_and_stock_is_restored(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, stock: 5);

        $order = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ])->json('data');

        $this->assertSame(4, $product->fresh()->stock);

        $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$order['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_buyer_cannot_cancel_an_already_paid_order(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'processing',
            'payment_status' => 'approved',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-PAID',
            'transaction_type' => 'sale',
        ]);

        $this->actingAs($customer, 'sanctum')->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(422);

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_other_buyers_cannot_cancel_someone_elses_order(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $owner = User::factory()->create(['role' => 'customer']);
        $stranger = User::factory()->create(['role' => 'customer']);

        $order = Order::create([
            'buyer_id' => $owner->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-OWNED',
            'transaction_type' => 'sale',
        ]);

        $this->actingAs($stranger, 'sanctum')->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(403);
    }

    public function test_admin_cannot_mark_a_pending_order_as_completed_directly(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-SKIP',
            'transaction_type' => 'sale',
        ]);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'completed',
        ])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_cancelling_an_order_restores_stock(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct($seller, $category, stock: 5);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-ADMIN-CANCEL',
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

        $product->decrement('stock');
        $this->assertSame(4, $product->fresh()->stock);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_cancel_stale_orders_command_cancels_old_pending_orders_and_restores_stock(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category, stock: 5);

        $staleOrder = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-STALE',
            'transaction_type' => 'sale',
        ]);

        // "created_at" no es fillable; se fuerza aparte para simular un
        // pedido viejo sin depender de mass assignment.
        $staleOrder->forceFill(['created_at' => now()->subHours(72)])->save();

        OrderItem::create([
            'order_id' => $staleOrder->id,
            'product_id' => $product->id,
            'type' => 'product',
            'quantity' => 1,
            'unit_price' => $product->price,
            'total' => $product->price,
        ]);

        $product->decrement('stock');

        $freshOrder = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-FRESH',
            'transaction_type' => 'sale',
        ]);

        $this->artisan('orders:cancel-stale')->run();

        $this->assertSame('cancelled', $staleOrder->fresh()->status);
        $this->assertSame('expired', $staleOrder->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame('pending', $freshOrder->fresh()->status, 'Un pedido reciente no debe cancelarse.');
    }
}
