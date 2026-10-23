<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WompiPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function configureWompi(): void
    {
        config([
            'mercova.wompi.public_key' => 'pub_test_123',
            'mercova.wompi.private_key' => 'prv_test_123',
            'mercova.wompi.integrity_secret' => 'integrity-secret',
            'mercova.wompi.events_secret' => 'events-secret',
            'mercova.wompi.sandbox' => true,
        ]);
    }

    private function createProduct(User $seller, Category $category): Product
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
            'stock' => 5,
            'status' => 'active',
        ]);
    }

    public function test_direct_purchase_returns_a_payment_url_when_wompi_is_configured(): void
    {
        $this->configureWompi();

        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.payment_status', 'pending');
        $this->assertNotNull($response->json('payment_url'));
        $this->assertStringStartsWith('https://checkout.wompi.co/p/', $response->json('payment_url'));
    }

    public function test_purchase_has_no_payment_url_when_wompi_is_not_configured(): void
    {
        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'debit_card',
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('payment_url'));
    }

    public function test_webhook_with_invalid_signature_is_rejected_and_does_not_update_the_order(): void
    {
        $this->configureWompi();

        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-TEST-REF',
            'transaction_type' => 'sale',
        ]);

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'txn-123',
                    'status' => 'APPROVED',
                    'reference' => 'ORDER-TEST-REF',
                ],
            ],
            'timestamp' => now()->timestamp,
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
                'checksum' => 'not-the-real-checksum',
            ],
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_webhook_with_valid_signature_marks_the_order_as_paid(): void
    {
        $this->configureWompi();

        $category = Category::create(['name' => 'Hogar', 'slug' => 'hogar']);
        $seller = User::factory()->create(['role' => 'seller']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct($seller, $category);

        $order = Order::create([
            'buyer_id' => $customer->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'total' => 450000,
            'payment_method' => 'debit_card',
            'payment_reference' => 'ORDER-TEST-REF',
            'transaction_type' => 'sale',
        ]);

        $timestamp = now()->timestamp;
        $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $transaction = [
            'id' => 'txn-123',
            'status' => 'APPROVED',
            'amount_in_cents' => 45000000,
            'reference' => 'ORDER-TEST-REF',
        ];

        $concat = $transaction['id'].$transaction['status'].$transaction['amount_in_cents'].$timestamp.'events-secret';
        $checksum = hash('sha256', $concat);

        $response = $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => $transaction],
            'timestamp' => $timestamp,
            'signature' => [
                'properties' => $properties,
                'checksum' => $checksum,
            ],
        ]);

        $response->assertOk();
        $order->refresh();
        $this->assertSame('approved', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('txn-123', $order->payment_transaction_id);
        $this->assertNotNull($order->paid_at);
    }
}
