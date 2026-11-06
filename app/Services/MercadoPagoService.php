<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\PaymentMethod\PaymentMethodClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Net\MPHttpClient;
use MercadoPago\Net\MPSearchRequest;
use MercadoPago\Webhook\WebhookSignatureValidator;

/**
 * Integración con Mercado Pago (Colombia) usando el SDK oficial
 * mercadopago/dx-php. Toda la app habla con Mercado Pago a través de esta
 * clase; las credenciales nunca salen del backend salvo la public key.
 *
 * Dos formas de cobrar una misma referencia nuestra (ORDER-, CART-, SUB-...),
 * que viaja a Mercado Pago como `external_reference`:
 *
 * - Checkout Pro (`createPreference()`): página de pago alojada por Mercado
 *   Pago, con tarjeta y PSE. Es el `payment_url` que ya devolvían los
 *   endpoints de checkout.
 * - Checkout API (`createPayment()`): el frontend tokeniza la tarjeta con
 *   MercadoPago.js (el número nunca toca este servidor) o elige un banco de
 *   PSE, y el backend crea el pago. Ver PaymentController.
 *
 * En ambos casos el resultado llega al webhook (`POST /api/webhooks/mercadopago`),
 * que solo trae el id del pago: el estado real siempre se consulta con
 * `findPayment()` usando el access token, nunca se toma del cuerpo del aviso.
 */
class MercadoPagoService
{
    public function __construct(
        private readonly ?string $publicKey,
        private readonly ?string $accessToken,
        private readonly ?string $webhookSecret,
        private readonly MPHttpClient $httpClient,
    ) {}

    public static function make(): self
    {
        return new self(
            publicKey: config('mercova.mercadopago.public_key'),
            accessToken: config('mercova.mercadopago.access_token'),
            webhookSecret: config('mercova.mercadopago.webhook_secret'),
            httpClient: new MercadoPagoHttpClient,
        );
    }

    /**
     * Sin public key (frontend) y access token (backend) no se puede cobrar:
     * la pasarela queda deshabilitada en vez de fallar a medias.
     */
    public function enabled(): bool
    {
        return filled($this->publicKey) && filled($this->accessToken);
    }

    public function publicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * Referencia única por intento de cobro. Usa un prefijo legible
     * (ORDER-{id}, CART-... o SUB-...) para identificar el pedido en el panel
     * de Mercado Pago sin tener que buscar en la base de datos.
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
     * Crea una preferencia de Checkout Pro y devuelve su `init_point`, la URL
     * a la que se redirige al comprador. Mercado Pago lo devuelve a
     * $returnUrl con ?payment_id=...&status=...&external_reference=...; ese
     * retorno es solo informativo, la confirmación real llega por webhook.
     */
    public function createPreference(string $reference, string $title, float $amount, string $returnUrl, ?string $payerEmail = null): string
    {
        $request = [
            'items' => [[
                'id' => $reference,
                'title' => $title,
                'quantity' => 1,
                'unit_price' => $amount,
                'currency_id' => 'COP',
            ]],
            'external_reference' => $reference,
            'back_urls' => ['success' => $returnUrl, 'failure' => $returnUrl, 'pending' => $returnUrl],
            'statement_descriptor' => 'MERCOVA',
            ...$this->notificationUrl(),
        ];

        if ($payerEmail) {
            $request['payer'] = ['email' => $payerEmail];
        }

        // Mercado Pago rechaza auto_return si la URL de retorno no es https
        // (p. ej. en local con http://localhost).
        if (str_starts_with($returnUrl, 'https://')) {
            $request['auto_return'] = 'approved';
        }

        $preference = (new PreferenceClient($this->httpClient))->create($request, $this->requestOptions());

        return (string) $preference->getResponse()->getContent()['init_point'];
    }

    /**
     * Crea un pago con Checkout API (tarjeta tokenizada o PSE). La misma
     * $idempotencyKey hace que un doble clic o un reintento de red no cobre
     * dos veces: Mercado Pago devuelve el pago ya creado.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     *
     * @throws MPApiException
     */
    public function createPayment(array $request, string $idempotencyKey): array
    {
        $payment = (new PaymentClient($this->httpClient))->create(
            [...$request, ...$this->notificationUrl()],
            $this->requestOptions(['X-Idempotency-Key: '.$idempotencyKey]),
        );

        return $payment->getResponse()->getContent();
    }

    /**
     * Estado real de un pago, consultado con nuestro access token. Null si
     * Mercado Pago no lo conoce (p. ej. la notificación de prueba del panel).
     *
     * @return array<string, mixed>|null
     *
     * @throws MPApiException Ante cualquier otro error de la API.
     */
    public function findPayment(string $paymentId): ?array
    {
        if (! ctype_digit($paymentId)) {
            return null;
        }

        try {
            $payment = (new PaymentClient($this->httpClient))->get((int) $paymentId, $this->requestOptions());
        } catch (MPApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        return $payment->getResponse()->getContent();
    }

    /**
     * Todos los intentos de pago de una referencia nuestra (tarjeta
     * rechazada y luego aprobada, PSE abandonado y reintentado...), del más
     * reciente al más viejo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentsByReference(string $reference): array
    {
        $search = (new PaymentClient($this->httpClient))->search(
            new MPSearchRequest(50, 0, [
                'external_reference' => $reference,
                'sort' => 'date_created',
                'criteria' => 'desc',
            ]),
            $this->requestOptions(),
        );

        return (array) ($search->getResponse()->getContent()['results'] ?? []);
    }

    /**
     * Bancos disponibles para PSE según Mercado Pago (cambian rara vez, por
     * eso se cachean 6 horas). En credenciales de prueba es la lista de
     * bancos de prueba.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function pseBanks(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember('mercadopago:pse-banks', now()->addHours(6), function (): array {
            $methods = (new PaymentMethodClient($this->httpClient))->list($this->requestOptions());
            $pse = collect($methods->getResponse()->getContent())->firstWhere('id', 'pse');

            return collect($pse['financial_institutions'] ?? [])
                ->map(fn (array $bank): array => ['id' => (string) $bank['id'], 'name' => (string) $bank['description']])
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    /**
     * Valida el header x-signature de una notificación: HMAC-SHA256 de
     * "id:{data.id};request-id:{x-request-id};ts:{ts};" con el secreto del
     * webhook. Sin secreto configurado no se puede validar nada.
     */
    public function hasValidWebhookSignature(?string $signature, ?string $requestId, ?string $dataId): bool
    {
        if (blank($this->webhookSecret)) {
            return false;
        }

        try {
            WebhookSignatureValidator::validate(
                $signature,
                $requestId,
                // Mercado Pago firma el id en minúsculas si es alfanumérico.
                $dataId !== null ? Str::lower($dataId) : null,
                $this->webhookSecret,
            );
        } catch (InvalidWebhookSignatureException) {
            return false;
        }

        return true;
    }

    /**
     * Traduce el estado de Mercado Pago al vocabulario que ya usan pedidos y
     * pagos de planes (approved/declined/voided/pending).
     */
    public function internalStatus(?string $mercadoPagoStatus): string
    {
        return match ($mercadoPagoStatus) {
            'approved' => 'approved',
            'rejected' => 'declined',
            'cancelled' => 'voided',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Mercado Pago solo puede notificar a una URL pública con https; en
     * local (http://localhost) se omite y el estado se obtiene consultando.
     *
     * @return array{notification_url?: string}
     */
    private function notificationUrl(): array
    {
        $url = route('webhooks.mercadopago');

        return str_starts_with($url, 'https://') ? ['notification_url' => $url] : [];
    }

    /**
     * @param  array<int, string>  $customHeaders
     */
    private function requestOptions(array $customHeaders = []): RequestOptions
    {
        $options = new RequestOptions;
        $options->setAccessToken((string) $this->accessToken);

        if ($customHeaders !== []) {
            $options->setCustomHeaders($customHeaders);
        }

        return $options;
    }
}
