<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Simula la API de Wompi (sandbox.wompi.co) con Http::fake() y firma los
 * eventos del webhook igual que Wompi: SHA-256 de los valores de
 * `signature.properties` + `timestamp` + secreto de eventos.
 */
trait FakesWompi
{
    protected const WOMPI_PUBLIC_KEY = 'pub_test_public';

    protected const WOMPI_PRIVATE_KEY = 'prv_test_private';

    protected const WOMPI_INTEGRITY_SECRET = 'test_integrity_secret';

    protected const WOMPI_EVENTS_SECRET = 'test_events_secret';

    /**
     * Transacciones que "existen" en Wompi, por id.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $wompiTransactions = [];

    protected function configureWompi(): void
    {
        config([
            'mercova.wompi.public_key' => self::WOMPI_PUBLIC_KEY,
            'mercova.wompi.private_key' => self::WOMPI_PRIVATE_KEY,
            'mercova.wompi.integrity_secret' => self::WOMPI_INTEGRITY_SECRET,
            'mercova.wompi.events_secret' => self::WOMPI_EVENTS_SECRET,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return match (true) {
                $path === '/v1/transactions' => Http::response(['data' => array_values(array_filter(
                    $this->wompiTransactions,
                    fn (array $transaction) => $transaction['reference'] === ($query['reference'] ?? null),
                ))]),
                str_starts_with($path, '/v1/transactions/') => isset($this->wompiTransactions[basename($path)])
                    ? Http::response(['data' => $this->wompiTransactions[basename($path)]])
                    : Http::response(['error' => ['type' => 'NOT_FOUND_ERROR']], 404),
                default => Http::response(['error' => 'Ruta no simulada'], 500),
            };
        });
    }

    /**
     * Registra una transacción en el Wompi simulado.
     *
     * @return array<string, mixed>
     */
    protected function fakeWompiTransaction(string $id, string $reference, string $status, float $amount, string $createdAt = '2026-11-11T10:00:00.000Z'): array
    {
        return $this->wompiTransactions[$id] = [
            'id' => $id,
            'created_at' => $createdAt,
            'amount_in_cents' => (int) round($amount * 100),
            'reference' => $reference,
            'currency' => 'COP',
            'payment_method_type' => 'CARD',
            'status' => $status,
            'status_message' => $status === 'DECLINED' ? 'Transacción rechazada' : null,
            'customer_email' => 'comprador@example.com',
        ];
    }

    /**
     * Registra la transacción y manda su evento firmado al webhook.
     */
    protected function sendWompiTransactionEvent(string $reference, string $status, float $amount, ?string $transactionId = null): TestResponse
    {
        $transactionId ??= '1234-'.random_int(1_000_000, 9_999_999).'-'.random_int(10000, 99999);
        $this->fakeWompiTransaction($transactionId, $reference, $status, $amount);

        return $this->sendWompiEvent($transactionId);
    }

    /**
     * Manda un evento "transaction.updated" firmado. El cuerpo lleva los datos
     * de la transacción registrada (o unos inventados si no existe).
     */
    protected function sendWompiEvent(string $transactionId, ?string $secret = self::WOMPI_EVENTS_SECRET): TestResponse
    {
        $transaction = $this->wompiTransactions[$transactionId] ?? [
            'id' => $transactionId,
            'status' => 'APPROVED',
            'amount_in_cents' => 100,
            'reference' => 'DESCONOCIDA',
        ];
        $timestamp = now()->timestamp;
        $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $checksum = hash('sha256', $transaction['id'].$transaction['status'].$transaction['amount_in_cents'].$timestamp.$secret);

        return $this->postJson('/api/webhooks/wompi', [
            'event' => 'transaction.updated',
            'data' => ['transaction' => $transaction],
            'environment' => 'test',
            'signature' => ['properties' => $properties, 'checksum' => strtoupper($checksum)],
            'timestamp' => $timestamp,
            'sent_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Parámetros del enlace de Web Checkout devuelto por la API.
     *
     * @return array<string, string>
     */
    protected function wompiCheckoutParameters(string $url): array
    {
        $this->assertStringStartsWith('https://checkout.wompi.co/p/?', $url);
        $parameters = [];

        foreach (explode('&', (string) parse_url($url, PHP_URL_QUERY)) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parameters[rawurldecode($key)] = rawurldecode($value);
        }

        return $parameters;
    }
}
