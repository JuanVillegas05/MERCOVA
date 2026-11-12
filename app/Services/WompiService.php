<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Integración con Wompi (Colombia). Toda la app habla con Wompi a través de
 * esta clase; las llaves nunca salen del backend salvo la llave pública.
 *
 * Cobro: Web Checkout (`checkoutUrl()`). El comprador paga en la página de
 * Wompi (tarjeta, PSE, Nequi, Bancolombia...); el número de tarjeta nunca
 * toca este servidor. Nuestra referencia (ORDER-, CART-, SUB-...) viaja como
 * `reference` y el enlace va firmado con el secreto de integridad, así que
 * el monto no se puede alterar.
 *
 * El resultado llega al webhook (`POST /api/webhooks/wompi`), cuyo checksum
 * se valida con el secreto de eventos. Aun así, el estado y el monto se
 * vuelven a consultar a la API (`findTransaction()`) antes de aplicarse.
 */
class WompiService
{
    private const CHECKOUT_URL = 'https://checkout.wompi.co/p/';

    private const CURRENCY = 'COP';

    public function __construct(
        private readonly ?string $publicKey,
        private readonly ?string $privateKey,
        private readonly ?string $integritySecret,
        private readonly ?string $eventsSecret,
    ) {}

    public static function make(): self
    {
        return new self(
            publicKey: config('mercova.wompi.public_key'),
            privateKey: config('mercova.wompi.private_key'),
            integritySecret: config('mercova.wompi.integrity_secret'),
            eventsSecret: config('mercova.wompi.events_secret'),
        );
    }

    /**
     * Sin llave pública y secreto de integridad no se puede generar un
     * enlace de pago válido: la pasarela queda deshabilitada.
     */
    public function enabled(): bool
    {
        return filled($this->publicKey) && filled($this->integritySecret);
    }

    public function publicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Referencia única por intento de cobro, con un prefijo legible
     * (ORDER-{id}, CART-... o SUB-...) para ubicarla en el panel de Wompi.
     */
    public function generateReference(string $prefix): string
    {
        return Str::upper($prefix).'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    public function amountInCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Enlace del Web Checkout. Wompi devuelve al comprador a $redirectUrl con
     * ?id={transacción}; ese retorno es solo informativo, la confirmación
     * real llega por webhook o consultando la transacción.
     */
    public function checkoutUrl(string $reference, float $amount, string $redirectUrl, ?string $customerEmail = null): string
    {
        $amountInCents = $this->amountInCents($amount);

        $query = [
            'public-key' => $this->publicKey,
            'currency' => self::CURRENCY,
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'signature:integrity' => $this->integritySignature($reference, $amountInCents),
            'redirect-url' => $redirectUrl,
        ];

        if ($customerEmail) {
            $query['customer-data:email'] = $customerEmail;
        }

        return self::CHECKOUT_URL.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * SHA-256 de "{referencia}{monto en centavos}{moneda}{secreto}".
     */
    public function integritySignature(string $reference, int $amountInCents): string
    {
        return hash('sha256', $reference.$amountInCents.self::CURRENCY.$this->integritySecret);
    }

    /**
     * Estado real de una transacción. Null si Wompi no la conoce.
     *
     * @return array<string, mixed>|null
     *
     * @throws RequestException Ante cualquier otro error de la API.
     */
    public function findTransaction(string $transactionId): ?array
    {
        if (! preg_match('/^[A-Za-z0-9-]{1,64}$/', $transactionId)) {
            return null;
        }

        $response = $this->http()->get('/transactions/'.$transactionId);

        if ($response->notFound()) {
            return null;
        }

        return (array) $response->throw()->json('data');
    }

    /**
     * Todos los intentos de una referencia nuestra (tarjeta rechazada y luego
     * aprobada, PSE abandonado y reintentado...). Requiere la llave privada;
     * sin ella devuelve una lista vacía.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function transactionsByReference(string $reference): array
    {
        if (blank($this->privateKey)) {
            return [];
        }

        return (array) $this->http()
            ->withToken((string) $this->privateKey)
            ->get('/transactions', ['reference' => $reference])
            ->throw()
            ->json('data', []);
    }

    /**
     * Valida un evento del webhook: SHA-256 de los valores de
     * `signature.properties` (leídos de `data`), seguidos de `timestamp` y
     * del secreto de eventos, debe coincidir con `signature.checksum`.
     *
     * @param  array<string, mixed>  $event
     */
    public function hasValidEventSignature(array $event): bool
    {
        $checksum = data_get($event, 'signature.checksum');
        $properties = data_get($event, 'signature.properties');
        $timestamp = data_get($event, 'timestamp');

        if (blank($this->eventsSecret) || ! is_string($checksum) || ! is_array($properties) || $properties === [] || $timestamp === null) {
            return false;
        }

        $payload = '';

        foreach ($properties as $property) {
            $value = data_get($event, 'data.'.$property);

            if (is_array($value)) {
                return false;
            }

            $payload .= (string) $value;
        }

        $expected = hash('sha256', $payload.$timestamp.$this->eventsSecret);

        return hash_equals($expected, Str::lower($checksum));
    }

    /**
     * Traduce el estado de Wompi al vocabulario que ya usan pedidos y pagos
     * de planes (approved/declined/voided/pending).
     */
    public function internalStatus(?string $wompiStatus): string
    {
        return match ($wompiStatus) {
            'APPROVED' => 'approved',
            'DECLINED', 'ERROR' => 'declined',
            'VOIDED' => 'voided',
            default => 'pending',
        };
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /**
     * Las llaves de prueba (pub_test_...) solo funcionan en el sandbox.
     */
    private function apiBaseUrl(): string
    {
        return str_starts_with((string) $this->publicKey, 'pub_prod_')
            ? 'https://production.wompi.co/v1'
            : 'https://sandbox.wompi.co/v1';
    }
}
