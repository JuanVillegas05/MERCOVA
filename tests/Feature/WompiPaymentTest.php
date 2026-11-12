<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesWompi;
use Tests\TestCase;

class WompiPaymentTest extends TestCase
{
    use FakesWompi;
    use RefreshDatabase;

    private function createProduct(User $seller, int $stock = 5, float $price = 450000): Product
    {
        $category = Category::firstOrCreate(['slug' => 'hogar'], ['name' => 'Hogar']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Silla Ergonómica',
            'slug' => 'silla-ergonomica-'.uniqid(),
            'description' => 'Silla de oficina con soporte lumbar.',
            'price' => $price,
            'commission_rate' => 0.06,
            'net_amount' => $price * 0.94,
            'transaction_type' => 'sale',
            'stock' => $stock,
            'status' => 'active',
        ]);
    }

    private function createPendingOrder(User $buyer, string $reference = 'ORDER-TEST-REF', float $total = 450000): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => User::factory()->create(['role' => 'seller'])->id,
            'status' => 'pending',
            'total' => $total,
            'payment_method' => 'debit_card',
            'payment_reference' => $reference,
            'transaction_type' => 'sale',
        ]);
    }

    private function expectedSignature(string $reference, int $amountInCents): string
    {
        return hash('sha256', $reference.$amountInCents.'COP'.self::WOMPI_INTEGRITY_SECRET);
    }

    public function test_direct_purchase_returns_a_signed_wompi_checkout_link_for_the_order_total(): void
    {
        $this->configureWompi();
        $customer = User::factory()->create(['email' => 'ana@example.com']);
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']));

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 2,
            'payment_method' => 'debit_card',
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', 'pending');
        $reference = $response->json('data.payment_reference');
        $parameters = $this->wompiCheckoutParameters($response->json('payment_url'));

        $this->assertSame(self::WOMPI_PUBLIC_KEY, $parameters['public-key']);
        $this->assertSame('COP', $parameters['currency']);
        $this->assertSame('90000000', $parameters['amount-in-cents']);
        $this->assertSame($reference, $parameters['reference']);
        $this->assertSame($this->expectedSignature($reference, 90000000), $parameters['signature:integrity']);
        $this->assertSame(route('payment.return'), $parameters['redirect-url']);
        $this->assertSame('ana@example.com', $parameters['customer-data:email']);
        $this->assertStringNotContainsString(self::WOMPI_INTEGRITY_SECRET, $response->getContent());
        Http::assertNothingSent();
    }

    public function test_cart_checkout_charges_the_total_of_every_seller_in_one_link(): void
    {
        $this->configureWompi();
        $customer = User::factory()->create();
        $first = $this->createProduct(User::factory()->create(['role' => 'seller']), price: 100000);
        $second = $this->createProduct(User::factory()->create(['role' => 'seller']), price: 50000);
        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $first->id, 'quantity' => 1])->assertCreated();
        $this->actingAs($customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $second->id, 'quantity' => 1])->assertCreated();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/checkout', [
            'recipient_name' => 'Cliente Mercova',
            'recipient_phone' => '+573001234567',
            'address' => 'Calle 1 # 2-34',
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'payment_method' => 'pse',
        ])->assertCreated();
        $this->assertCount(2, $response->json('data'));

        // Reintentar el pago desde uno solo de los pedidos también cobra el carrito completo.
        $retry = $this->actingAs($customer, 'sanctum')->getJson('/api/orders/'.$response->json('data.0.id').'/payment')->assertOk();

        $this->assertSame('15000000', $this->wompiCheckoutParameters($response->json('payment_url'))['amount-in-cents']);
        $this->assertSame('15000000', $this->wompiCheckoutParameters($retry->json('payment_url'))['amount-in-cents']);
    }

    public function test_order_payment_links_return_to_the_frontend_only_when_the_url_is_allowed(): void
    {
        $this->configureWompi();
        config(['mercova.frontend_url' => 'https://app.mercova.test']);
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']), stock: 5);

        $allowed = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'return_url' => 'https://app.mercova.test/pago/resultado',
        ])->assertCreated();
        $foreign = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'return_url' => 'https://evil.test/robar',
        ])->assertCreated();
        $retry = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/orders/'.$allowed->json('data.id').'/payment?return_url='.urlencode('https://app.mercova.test/pago/resultado'))
            ->assertOk();

        $this->assertSame('https://app.mercova.test/pago/resultado', $this->wompiCheckoutParameters($allowed->json('payment_url'))['redirect-url']);
        $this->assertSame(route('payment.return'), $this->wompiCheckoutParameters($foreign->json('payment_url'))['redirect-url']);
        $this->assertSame('https://app.mercova.test/pago/resultado', $this->wompiCheckoutParameters($retry->json('payment_url'))['redirect-url']);
    }

    public function test_purchase_has_no_payment_url_when_wompi_is_not_configured(): void
    {
        Http::preventStrayRequests();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']));

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('payment_url'));
    }

    public function test_webhook_with_invalid_checksum_is_rejected_without_querying_wompi(): void
    {
        $this->configureWompi();
        $order = $this->createPendingOrder(User::factory()->create());
        $this->fakeWompiTransaction('555-1', 'ORDER-TEST-REF', 'APPROVED', 450000);

        $this->sendWompiEvent('555-1', secret: 'not-the-real-secret')->assertUnauthorized();

        $this->assertSame('pending', $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_webhook_is_rejected_when_no_events_secret_is_configured(): void
    {
        $this->configureWompi();
        config(['mercova.wompi.events_secret' => null]);
        $order = $this->createPendingOrder(User::factory()->create());
        $this->fakeWompiTransaction('555-1', 'ORDER-TEST-REF', 'APPROVED', 450000);

        $this->sendWompiEvent('555-1')->assertUnauthorized();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_approved_event_marks_the_order_as_paid_using_the_transaction_from_the_api(): void
    {
        $this->configureWompi();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendWompiTransactionEvent('ORDER-TEST-REF', 'APPROVED', 450000, transactionId: '777-1')->assertOk();

        $order->refresh();
        $this->assertSame('approved', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('777-1', $order->payment_transaction_id);
        $this->assertNotNull($order->paid_at);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://sandbox.wompi.co/v1/transactions/777-1');
    }

    public function test_declined_attempt_keeps_the_order_open_so_the_buyer_can_retry(): void
    {
        $this->configureWompi();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']), stock: 5);
        $order = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 2])
            ->json('data');

        $this->sendWompiTransactionEvent($order['payment_reference'], 'DECLINED', 900000)->assertOk();

        $this->assertSame('pending', Order::find($order['id'])->status);
        $this->assertSame('declined', Order::find($order['id'])->payment_status);
        $this->assertSame(3, $product->fresh()->stock, 'El stock sigue reservado mientras el comprador reintenta.');

        $this->sendWompiTransactionEvent($order['payment_reference'], 'APPROVED', 900000)->assertOk();

        $this->assertSame('processing', Order::find($order['id'])->status);
        $this->assertSame('approved', Order::find($order['id'])->payment_status);
    }

    public function test_approved_order_is_never_downgraded_by_a_later_event(): void
    {
        $this->configureWompi();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendWompiTransactionEvent('ORDER-TEST-REF', 'APPROVED', 450000)->assertOk();
        $this->sendWompiTransactionEvent('ORDER-TEST-REF', 'DECLINED', 450000)->assertOk();

        $this->assertSame('approved', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_approved_transaction_for_a_different_amount_does_not_mark_the_order_as_paid(): void
    {
        $this->configureWompi();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendWompiTransactionEvent('ORDER-TEST-REF', 'APPROVED', 1000)->assertOk();

        $this->assertSame('amount_mismatch', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_stale_order_with_a_declined_payment_is_cancelled_and_its_stock_restored(): void
    {
        $this->configureWompi();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']), stock: 5);
        $order = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 2])
            ->json('data');
        $this->sendWompiTransactionEvent($order['payment_reference'], 'DECLINED', 900000)->assertOk();

        $this->travel(config('mercova.order_expiry_hours') + 1)->hours();
        $this->artisan('orders:cancel-stale')->assertSuccessful();

        $this->assertSame('cancelled', Order::find($order['id'])->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_payment_link_uses_the_amount_stored_in_the_database_and_only_trusted_return_urls(): void
    {
        $this->configureWompi();
        config(['mercova.frontend_url' => 'https://app.mercova.test']);
        $customer = User::factory()->create();
        $this->createPendingOrder($customer, total: 450000);

        $allowed = $this->actingAs($customer, 'sanctum')->postJson('/api/payments', [
            'reference' => 'ORDER-TEST-REF',
            'amount_in_cents' => 100,
            'return_url' => 'https://app.mercova.test/pago/resultado',
        ])->assertCreated();
        $foreign = $this->actingAs($customer, 'sanctum')->postJson('/api/payments', [
            'reference' => 'ORDER-TEST-REF',
            'return_url' => 'https://evil.test/robar',
        ])->assertCreated();

        $parameters = $this->wompiCheckoutParameters($allowed->json('data.payment_url'));
        $this->assertSame('45000000', $parameters['amount-in-cents']);
        $this->assertSame($this->expectedSignature('ORDER-TEST-REF', 45000000), $parameters['signature:integrity']);
        $this->assertSame('https://app.mercova.test/pago/resultado', $parameters['redirect-url']);
        $this->assertSame(route('payment.return'), $this->wompiCheckoutParameters($foreign->json('data.payment_url'))['redirect-url']);
    }

    public function test_buyer_cannot_get_a_payment_link_for_someone_elses_reference(): void
    {
        $this->configureWompi();
        $this->createPendingOrder(User::factory()->create());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/payments', ['reference' => 'ORDER-TEST-REF'])
            ->assertNotFound();
    }

    public function test_an_already_paid_reference_cannot_be_charged_again(): void
    {
        $this->configureWompi();
        $customer = User::factory()->create();
        $this->createPendingOrder($customer)->update(['payment_status' => 'approved', 'status' => 'processing']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/payments', ['reference' => 'ORDER-TEST-REF'])
            ->assertNotFound();
    }

    public function test_payment_link_is_unavailable_when_wompi_is_not_configured(): void
    {
        $customer = User::factory()->create();
        $this->createPendingOrder($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/payments', ['reference' => 'ORDER-TEST-REF'])
            ->assertServiceUnavailable();
    }

    public function test_transaction_status_is_only_visible_to_its_owner(): void
    {
        $this->configureWompi();
        $owner = User::factory()->create();
        $order = $this->createPendingOrder($owner);
        $this->fakeWompiTransaction('903-1', 'ORDER-TEST-REF', 'APPROVED', 450000);

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/payments/903-1')->assertNotFound();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->actingAs($owner, 'sanctum')->getJson('/api/payments/903-1')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.wompi_status', 'APPROVED')
            ->assertJsonMissingPath('data.customer_email');
        $this->assertSame('approved', $order->fresh()->payment_status);
    }

    public function test_checkout_config_exposes_only_the_public_key(): void
    {
        $this->configureWompi();

        $response = $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertExactJson(['data' => [
                'enabled' => true,
                'provider' => 'wompi',
                'public_key' => self::WOMPI_PUBLIC_KEY,
            ]]);

        foreach ([self::WOMPI_PRIVATE_KEY, self::WOMPI_INTEGRITY_SECRET, self::WOMPI_EVENTS_SECRET] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_checkout_config_keeps_the_same_shape_when_wompi_is_not_configured(): void
    {
        $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertExactJson(['data' => [
                'enabled' => false,
                'provider' => 'wompi',
                'public_key' => null,
            ]]);
    }
}
