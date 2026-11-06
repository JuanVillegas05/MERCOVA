<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesMercadoPago;
use Tests\TestCase;

class MercadoPagoPaymentTest extends TestCase
{
    use FakesMercadoPago;
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

    /**
     * @return array<string, mixed>
     */
    private function psePayload(string $reference): array
    {
        return [
            'reference' => $reference,
            'payment_method' => 'pse',
            'pse' => ['financial_institution' => '1022', 'entity_type' => 'individual'],
            'payer' => [
                'first_name' => 'Ana',
                'last_name' => 'Gómez',
                'identification' => ['type' => 'CC', 'number' => '1032456789'],
                'phone' => ['area_code' => '57', 'number' => '3001234567'],
                'address' => [
                    'zip_code' => '110111',
                    'street_name' => 'Calle 10',
                    'street_number' => '20-30',
                    'neighborhood' => 'Chapinero',
                    'city' => 'Bogotá',
                    'federal_unit' => 'Cundinamarca',
                ],
            ],
        ];
    }

    public function test_direct_purchase_returns_a_checkout_pro_url_for_the_order_total(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']));

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'product_id' => $product->id,
            'quantity' => 2,
            'payment_method' => 'debit_card',
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', 'pending');
        $this->assertStringStartsWith('https://www.mercadopago.com.co/checkout/', $response->json('payment_url'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/checkout/preferences')
            && $request->hasHeader('Authorization', 'Bearer '.self::MERCADOPAGO_ACCESS_TOKEN)
            && $request['external_reference'] === $response->json('data.payment_reference')
            && (float) $request['items'][0]['unit_price'] === 900000.0);
    }

    public function test_cart_checkout_charges_the_total_of_every_seller_in_one_preference(): void
    {
        $this->configureMercadoPago();
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
        $this->actingAs($customer, 'sanctum')->getJson('/api/orders/'.$response->json('data.0.id').'/payment')->assertOk();

        $preferences = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/checkout/preferences'));
        $this->assertCount(2, $preferences);
        $preferences->each(fn ($pair) => $this->assertSame(150000.0, (float) $pair[0]['items'][0]['unit_price']));
    }

    public function test_purchase_has_no_payment_url_when_mercado_pago_is_not_configured(): void
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

    public function test_webhook_with_invalid_signature_is_rejected_without_querying_mercado_pago(): void
    {
        $this->configureMercadoPago();
        $order = $this->createPendingOrder(User::factory()->create());
        $this->fakeMercadoPagoPayment('555', 'ORDER-TEST-REF', 'approved', 450000);

        $this->sendMercadoPagoWebhook('555', secret: 'not-the-real-secret')->assertUnauthorized();

        $this->assertSame('pending', $order->fresh()->payment_status);
        Http::assertNothingSent();
    }

    public function test_webhook_is_rejected_when_no_webhook_secret_is_configured(): void
    {
        $this->configureMercadoPago();
        config(['mercova.mercadopago.webhook_secret' => null]);
        $order = $this->createPendingOrder(User::factory()->create());
        $this->fakeMercadoPagoPayment('555', 'ORDER-TEST-REF', 'approved', 450000);

        $this->sendMercadoPagoWebhook('555')->assertUnauthorized();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_approved_webhook_marks_the_order_as_paid_using_the_status_from_the_api(): void
    {
        $this->configureMercadoPago();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendMercadoPagoPaymentNotification('ORDER-TEST-REF', 'approved', 450000, paymentId: '777')->assertOk();

        $order->refresh();
        $this->assertSame('approved', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('777', $order->payment_transaction_id);
        $this->assertNotNull($order->paid_at);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/payments/777'));
    }

    public function test_rejected_attempt_keeps_the_order_open_so_the_buyer_can_retry(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']), stock: 5);
        $order = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 2])
            ->json('data');

        $this->sendMercadoPagoPaymentNotification($order['payment_reference'], 'rejected', 900000)->assertOk();

        $this->assertSame('pending', Order::find($order['id'])->status);
        $this->assertSame('declined', Order::find($order['id'])->payment_status);
        $this->assertSame(3, $product->fresh()->stock, 'El stock sigue reservado mientras el comprador reintenta.');

        $this->sendMercadoPagoPaymentNotification($order['payment_reference'], 'approved', 900000)->assertOk();

        $this->assertSame('processing', Order::find($order['id'])->status);
        $this->assertSame('approved', Order::find($order['id'])->payment_status);
    }

    public function test_approved_order_is_never_downgraded_by_a_later_event(): void
    {
        $this->configureMercadoPago();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendMercadoPagoPaymentNotification('ORDER-TEST-REF', 'approved', 450000)->assertOk();
        $this->sendMercadoPagoPaymentNotification('ORDER-TEST-REF', 'rejected', 450000)->assertOk();

        $this->assertSame('approved', $order->fresh()->payment_status);
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_approved_payment_for_a_different_amount_does_not_mark_the_order_as_paid(): void
    {
        $this->configureMercadoPago();
        $order = $this->createPendingOrder(User::factory()->create());

        $this->sendMercadoPagoPaymentNotification('ORDER-TEST-REF', 'approved', 1000)->assertOk();

        $this->assertSame('amount_mismatch', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_stale_order_with_a_rejected_payment_is_cancelled_and_its_stock_restored(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $product = $this->createProduct(User::factory()->create(['role' => 'seller']), stock: 5);
        $order = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/orders', ['product_id' => $product->id, 'quantity' => 2])
            ->json('data');
        $this->sendMercadoPagoPaymentNotification($order['payment_reference'], 'rejected', 900000)->assertOk();

        $this->travel(config('mercova.order_expiry_hours') + 1)->hours();
        $this->artisan('orders:cancel-stale')->assertSuccessful();

        $this->assertSame('cancelled', Order::find($order['id'])->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_card_payment_is_charged_for_the_amount_stored_in_the_database(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create(['email' => 'ana@example.com']);
        $order = $this->createPendingOrder($customer, total: 450000);
        $this->mercadoPagoCreatedPayment = [
            'id' => 901,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'ORDER-TEST-REF',
            'transaction_amount' => 450000,
            'currency_id' => 'COP',
            'payment_method_id' => 'visa',
        ];
        $idempotencyKey = '6f1c2a4e-8d3b-4c7a-9e2f-1b5d7c9a3e01';

        $this->actingAs($customer, 'sanctum')
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/payments', [
                'reference' => 'ORDER-TEST-REF',
                'payment_method' => 'card',
                'transaction_amount' => 1,
                'card' => ['token' => 'card-token-abc', 'payment_method_id' => 'visa', 'issuer_id' => 1, 'installments' => 3],
                // El Brick de tarjeta puede mandar tipos de documento que PSE no acepta.
                'payer' => ['identification' => ['type' => 'Otro', 'number' => '987654']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', '901')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.redirect_url', null);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/payments')
            && $request->hasHeader('X-Idempotency-Key', $idempotencyKey)
            && (float) $request['transaction_amount'] === 450000.0
            && $request['token'] === 'card-token-abc'
            && $request['installments'] === 3
            && $request['external_reference'] === 'ORDER-TEST-REF'
            && $request['payer']['email'] === 'ana@example.com');
        $this->assertSame('approved', $order->fresh()->payment_status);
    }

    public function test_pse_payment_sends_the_selected_bank_and_returns_the_bank_redirect_url(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $order = $this->createPendingOrder($customer);
        $this->mercadoPagoCreatedPayment = [
            'id' => 902,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_transfer',
            'external_reference' => 'ORDER-TEST-REF',
            'transaction_amount' => 450000,
            'currency_id' => 'COP',
            'payment_method_id' => 'pse',
            'transaction_details' => ['external_resource_url' => 'https://banco.test/pse/autorizar'],
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/payments', $this->psePayload('ORDER-TEST-REF'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.redirect_url', 'https://banco.test/pse/autorizar');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/payments')
            && $request['payment_method_id'] === 'pse'
            && $request['transaction_details']['financial_institution'] === '1022'
            && $request['payer']['entity_type'] === 'individual'
            && $request['payer']['identification'] === ['type' => 'CC', 'number' => '1032456789']
            && $request['callback_url'] === route('payment.return')
            && filled($request['additional_info']['ip_address']));
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_pse_payment_requires_a_bank_and_the_payer_document(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $this->createPendingOrder($customer);
        $payload = $this->psePayload('ORDER-TEST-REF');
        unset($payload['pse']['financial_institution'], $payload['payer']['identification']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/payments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pse.financial_institution', 'payer.identification.type', 'payer.identification.number']);

        Http::assertNothingSent();
    }

    public function test_buyer_cannot_pay_someone_elses_reference(): void
    {
        $this->configureMercadoPago();
        $this->createPendingOrder(User::factory()->create());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/payments', $this->psePayload('ORDER-TEST-REF'))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_an_already_paid_reference_cannot_be_charged_again(): void
    {
        $this->configureMercadoPago();
        $customer = User::factory()->create();
        $this->createPendingOrder($customer)->update(['payment_status' => 'approved', 'status' => 'processing']);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/payments', $this->psePayload('ORDER-TEST-REF'))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_payment_status_is_only_visible_to_its_owner(): void
    {
        $this->configureMercadoPago();
        $owner = User::factory()->create();
        $order = $this->createPendingOrder($owner);
        $this->fakeMercadoPagoPayment('903', 'ORDER-TEST-REF', 'approved', 450000);

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/payments/903')->assertNotFound();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->actingAs($owner, 'sanctum')->getJson('/api/payments/903')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->assertSame('approved', $order->fresh()->payment_status);
    }

    public function test_checkout_config_exposes_the_public_key_and_pse_banks_but_no_secrets(): void
    {
        $this->configureMercadoPago();

        $response = $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.public_key', 'TEST-public-key')
            ->assertJsonPath('data.pse_banks', [
                ['id' => '1022', 'name' => 'Banco Universitario'],
                ['id' => '1007', 'name' => 'Bancolombia'],
            ]);

        $this->assertStringNotContainsString(self::MERCADOPAGO_ACCESS_TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::MERCADOPAGO_WEBHOOK_SECRET, $response->getContent());
    }
}
