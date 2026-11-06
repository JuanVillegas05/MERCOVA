<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Simula la API de Mercado Pago (api.mercadopago.com) con Http::fake() y
 * firma los webhooks igual que Mercado Pago: HMAC-SHA256 de
 * "id:{data.id};request-id:{x-request-id};ts:{ts};" con el secreto.
 */
trait FakesMercadoPago
{
    protected const MERCADOPAGO_WEBHOOK_SECRET = 'webhook-secret';

    protected const MERCADOPAGO_ACCESS_TOKEN = 'TEST-access-token';

    /**
     * Pagos que "existen" en Mercado Pago, por id.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $mercadoPagoPayments = [];

    /**
     * Lo que responde POST /v1/payments (Checkout API).
     *
     * @var array<string, mixed>
     */
    protected array $mercadoPagoCreatedPayment = [];

    protected function configureMercadoPago(): void
    {
        config([
            'mercova.mercadopago.public_key' => 'TEST-public-key',
            'mercova.mercadopago.access_token' => self::MERCADOPAGO_ACCESS_TOKEN,
            'mercova.mercadopago.webhook_secret' => self::MERCADOPAGO_WEBHOOK_SECRET,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match (true) {
                $path === '/checkout/preferences' => Http::response([
                    'id' => 'pref-123',
                    'init_point' => 'https://www.mercadopago.com.co/checkout/v1/redirect?pref_id=pref-123',
                ], 201),
                $path === '/v1/payments' && $request->method() === 'POST' => Http::response($this->mercadoPagoCreatedPayment, 201),
                $path === '/v1/payments/search' => Http::response(['results' => array_values(array_filter(
                    $this->mercadoPagoPayments,
                    fn (array $payment) => $payment['external_reference'] === ($query['external_reference'] ?? null),
                ))]),
                str_starts_with($path, '/v1/payments/') => isset($this->mercadoPagoPayments[basename($path)])
                    ? Http::response($this->mercadoPagoPayments[basename($path)])
                    : Http::response(['message' => 'Payment not found'], 404),
                $path === '/v1/payment_methods' => Http::response([
                    ['id' => 'visa', 'payment_type_id' => 'credit_card'],
                    ['id' => 'pse', 'payment_type_id' => 'bank_transfer', 'financial_institutions' => [
                        ['id' => '1022', 'description' => 'Banco Universitario'],
                        ['id' => '1007', 'description' => 'Bancolombia'],
                    ]],
                ]),
                default => Http::response(['message' => 'Ruta no simulada'], 500),
            };
        });
    }

    /**
     * Registra un pago en el Mercado Pago simulado.
     *
     * @return array<string, mixed>
     */
    protected function fakeMercadoPagoPayment(string $id, string $reference, string $status, float $amount): array
    {
        return $this->mercadoPagoPayments[$id] = [
            'id' => (int) $id,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'cc_rejected_other_reason',
            'external_reference' => $reference,
            'transaction_amount' => $amount,
            'currency_id' => 'COP',
            'payment_method_id' => 'visa',
        ];
    }

    /**
     * Registra el pago y manda su notificación firmada al webhook.
     */
    protected function sendMercadoPagoPaymentNotification(string $reference, string $status, float $amount, ?string $paymentId = null): TestResponse
    {
        $paymentId ??= (string) random_int(1_000_000, 9_999_999);
        $this->fakeMercadoPagoPayment($paymentId, $reference, $status, $amount);

        return $this->sendMercadoPagoWebhook($paymentId);
    }

    protected function sendMercadoPagoWebhook(string $paymentId, ?string $secret = self::MERCADOPAGO_WEBHOOK_SECRET): TestResponse
    {
        $timestamp = (string) now()->timestamp;
        $requestId = 'req-'.$paymentId;
        $hash = hash_hmac('sha256', "id:{$paymentId};request-id:{$requestId};ts:{$timestamp};", (string) $secret);

        return $this->postJson(
            "/api/webhooks/mercadopago?data.id={$paymentId}&type=payment",
            ['action' => 'payment.updated', 'type' => 'payment', 'data' => ['id' => $paymentId]],
            ['x-signature' => "ts={$timestamp},v1={$hash}", 'x-request-id' => $requestId],
        );
    }
}
