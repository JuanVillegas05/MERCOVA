<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Integración con la pasarela de pagos Wompi (https://wompi.co) usando su
 * "Web Checkout" (página de pago alojada por Wompi, sin tener que guardar
 * tarjetas ni manejar PCI-DSS nosotros mismos).
 *
 * Flujo:
 * 1. Al crear un pedido (o un grupo de pedidos desde /api/checkout) se genera
 *    una referencia única y se firma con la llave de integridad.
 * 2. Esa referencia + firma arman la URL de `checkoutUrl()`, a la que el
 *    frontend redirige al comprador para pagar.
 * 3. Wompi notifica el resultado al webhook (`POST /api/webhooks/wompi`),
 *    firmado con la llave de eventos: `verifyEventSignature()` lo valida
 *    antes de marcar el pedido como pagado.
 * 4. Como refuerzo (Wompi lo recomienda porque un webhook se puede perder),
 *    `fetchTransaction()` permite re-consultar el estado real de una
 *    transacción contra la API de Wompi usando la llave privada.
 */
class WompiService
{
    public function __construct(
        private readonly ?string $publicKey = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $integritySecret = null,
        private readonly ?string $eventsSecret = null,
        private readonly bool $sandbox = true,
    ) {}

    public static function make(): self
    {
        return new self(
            publicKey: config('mercova.wompi.public_key'),
            privateKey: config('mercova.wompi.private_key'),
            integritySecret: config('mercova.wompi.integrity_secret'),
            eventsSecret: config('mercova.wompi.events_secret'),
            sandbox: (bool) config('mercova.wompi.sandbox', true),
        );
    }

    /**
     * Sin las 3 llaves necesarias para cobrar (pública + privada + de
     * integridad), la pasarela queda deshabilitada mencionando por qué en
     * vez de fallar a medias cuando el comprador llega al checkout.
     */
    public function enabled(): bool
    {
        return filled($this->publicKey) && filled($this->privateKey) && filled($this->integritySecret);
    }

    public function sandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * Referencia única por intento de cobro. Usa un prefijo legible
     * (ORDER-{id} o CART-{buyer_id}) para poder identificar el pedido en el
     * panel de comercios de Wompi sin tener que buscar en la base de datos.
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
     * Firma de integridad que exige Wompi para el Web Checkout:
     * sha256(referencia + monto_en_centavos + moneda + secreto_integridad).
     */
    public function integritySignature(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        return hash('sha256', $reference.$amountInCents.$currency.$this->integritySecret);
    }

    /**
     * URL del Web Checkout de Wompi a la que se debe redirigir al comprador.
     * $redirectUrl es a dónde vuelve Wompi (en nuestro dominio) después del
     * intento de pago; la confirmación real siempre llega por el webhook,
     * nunca hay que confiar solo en este retorno.
     */
    public function checkoutUrl(string $reference, float $amount, ?string $redirectUrl = null, string $currency = 'COP'): string
    {
        $cents = $this->amountInCents($amount);

        $params = [
            'public-key' => $this->publicKey,
            'currency' => $currency,
            'amount-in-cents' => $cents,
            'reference' => $reference,
            'signature:integrity' => $this->integritySignature($reference, $cents, $currency),
        ];

        if ($redirectUrl) {
            $params['redirect-url'] = $redirectUrl;
        }

        return 'https://checkout.wompi.co/p/?'.http_build_query($params);
    }

    public function apiBaseUrl(): string
    {
        return $this->sandbox ? 'https://sandbox.wompi.co/v1' : 'https://production.wompi.co/v1';
    }

    /**
     * Re-consulta una transacción directamente contra Wompi (con la llave
     * privada) para confirmar su estado real. Se usa en el webhook como
     * verificación adicional y queda disponible para revisiones manuales.
     *
     * @return array<string, mixed>|null
     */
    public function fetchTransaction(string $transactionId): ?array
    {
        if (! $this->privateKey) {
            return null;
        }

        $response = Http::withToken($this->privateKey)
            ->get("{$this->apiBaseUrl()}/transactions/{$transactionId}");

        return $response->successful() ? $response->json('data') : null;
    }

    /**
     * Valida la firma que Wompi manda en cada evento de webhook. El payload
     * trae `signature.properties` (rutas dentro del propio payload a
     * concatenar, en orden), `timestamp` y `signature.checksum` a comparar
     * contra sha256(propiedades_concatenadas + timestamp + secreto_eventos).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyEventSignature(array $payload): bool
    {
        if (! $this->eventsSecret) {
            return false;
        }

        $properties = data_get($payload, 'signature.properties');
        $checksum = data_get($payload, 'signature.checksum');
        $timestamp = data_get($payload, 'timestamp');

        if (! is_array($properties) || ! $checksum || ! $timestamp) {
            return false;
        }

        $concat = '';
        foreach ($properties as $property) {
            // Wompi documenta las rutas de "properties" relativas a "data"
            // (p. ej. "transaction.id" == $payload['data']['transaction']['id']),
            // pero por si acaso se prueba también la ruta tal cual vino.
            $value = data_get($payload, "data.{$property}") ?? data_get($payload, $property);
            $concat .= (string) $value;
        }
        $concat .= $timestamp.$this->eventsSecret;

        return hash_equals(hash('sha256', $concat), Str::lower((string) $checksum));
    }
}
