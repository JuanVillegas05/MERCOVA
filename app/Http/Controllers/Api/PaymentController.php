<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FrontendRedirect;
use App\Services\MercadoPagoPaymentService;
use App\Services\MercadoPagoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MercadoPago\Exceptions\MPApiException;
use Throwable;

/**
 * Checkout propio (Checkout API de Mercado Pago) para pagar una referencia
 * ya creada por POST /api/orders, POST /api/checkout o
 * POST /api/subscriptions/checkout:
 *
 * - Tarjeta: el frontend tokeniza la tarjeta con MercadoPago.js usando la
 *   public key y solo manda el token; el número nunca pasa por aquí.
 * - PSE: el comprador elige su banco (GET /api/payments/config) y se le
 *   redirige a `redirect_url` para autorizar el débito en su banco.
 *
 * El monto y la descripción siempre se calculan aquí desde la base de datos.
 */
class PaymentController extends Controller
{
    /** Tipos de documento que acepta PSE para el pagador. */
    private const PSE_IDENTIFICATION_TYPES = ['CC', 'CE', 'NIT', 'PP'];

    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly MercadoPagoPaymentService $payments,
        private readonly FrontendRedirect $frontendRedirect,
    ) {}

    /**
     * Lo que el frontend necesita para pintar el checkout: la public key
     * (pública por diseño) y los bancos de PSE.
     */
    public function config(): JsonResponse
    {
        if (! $this->mercadoPago->enabled()) {
            return response()->json(['data' => ['enabled' => false, 'public_key' => null, 'pse_banks' => []]]);
        }

        try {
            $banks = $this->mercadoPago->pseBanks();
        } catch (Throwable $e) {
            // Sin bancos se puede seguir pagando con tarjeta.
            report($e);
            $banks = [];
        }

        return response()->json([
            'data' => [
                'enabled' => true,
                'public_key' => $this->mercadoPago->publicKey(),
                'pse_banks' => $banks,
                'identification_types' => self::PSE_IDENTIFICATION_TYPES,
            ],
        ]);
    }

    /**
     * Cobra una referencia con tarjeta (token) o PSE. Mandar el mismo header
     * `X-Idempotency-Key` (UUID) en los reintentos evita cobrar dos veces.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->mercadoPago->enabled()) {
            abort(503, 'La pasarela de pagos no está configurada todavía.');
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'payment_method' => ['required', Rule::in(['card', 'pse'])],
            'return_url' => ['nullable', 'string', 'url', 'max:2048'],

            'card' => ['exclude_unless:payment_method,card', 'required', 'array'],
            'card.token' => ['exclude_unless:payment_method,card', 'required', 'string', 'max:255'],
            'card.payment_method_id' => ['exclude_unless:payment_method,card', 'required', 'string', 'max:50'],
            'card.issuer_id' => ['exclude_unless:payment_method,card', 'nullable', 'integer'],
            'card.installments' => ['exclude_unless:payment_method,card', 'required', 'integer', 'between:1,36'],

            'pse' => ['exclude_unless:payment_method,pse', 'required', 'array'],
            'pse.financial_institution' => ['exclude_unless:payment_method,pse', 'required', 'string', 'max:20'],
            'pse.entity_type' => ['exclude_unless:payment_method,pse', 'required', Rule::in(['individual', 'association'])],

            'payer' => ['nullable', 'array'],
            'payer.email' => ['nullable', 'email', 'max:255'],
            // En tarjeta el tipo lo elige el Brick de Mercado Pago; en PSE
            // solo se aceptan los tipos que PSE admite.
            'payer.identification.type' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:20', Rule::when(
                $request->input('payment_method') === 'pse',
                [Rule::in(self::PSE_IDENTIFICATION_TYPES)],
            )],
            'payer.identification.number' => ['required_if:payment_method,pse', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'payer.first_name' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:60'],
            'payer.last_name' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:60'],
            'payer.phone.area_code' => ['required_if:payment_method,pse', 'nullable', 'digits_between:1,4'],
            'payer.phone.number' => ['required_if:payment_method,pse', 'nullable', 'digits_between:7,10'],
            'payer.address.zip_code' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:10'],
            'payer.address.street_name' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:100'],
            'payer.address.street_number' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:20'],
            'payer.address.neighborhood' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:60'],
            'payer.address.city' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:60'],
            'payer.address.federal_unit' => ['required_if:payment_method,pse', 'nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $paymentData = $validated['payment_method'] === 'card'
            ? $this->cardPaymentData($validated, $user->email)
            : $this->psePaymentData($validated, $user->email, $request->ip());

        $idempotencyKey = (string) $request->header('X-Idempotency-Key');

        try {
            $payment = $this->payments->pay(
                $user,
                $validated['reference'],
                $paymentData,
                Str::isUuid($idempotencyKey) ? $idempotencyKey : (string) Str::uuid(),
            );
        } catch (MPApiException $e) {
            Log::warning('Mercado Pago rechazó la creación del pago.', [
                'reference' => $validated['reference'],
                'status' => $e->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
            ]);

            abort($e->getStatusCode() >= 500 ? 503 : 422, 'Mercado Pago no pudo procesar el pago. Revisa los datos e intenta de nuevo.');
        } catch (ConnectionException $e) {
            report($e);
            abort(503, 'No pudimos conectar con Mercado Pago. Intenta de nuevo en unos minutos.');
        }

        return response()->json(['data' => $this->summary($payment)], 201);
    }

    /**
     * Estado de un pago de Mercado Pago del usuario (p. ej. al volver del
     * banco en PSE). Se consulta directamente a Mercado Pago y se aplica,
     * por si el webhook aún no llegó. 404 si el pago no es del usuario.
     */
    public function show(Request $request, string $paymentId): JsonResponse
    {
        if (! $this->mercadoPago->enabled()) {
            abort(404);
        }

        $payment = $this->mercadoPago->findPayment($paymentId);

        if ($payment === null || ! $this->payments->belongsTo($request->user(), (string) ($payment['external_reference'] ?? ''))) {
            abort(404);
        }

        $this->payments->apply($payment);

        return response()->json(['data' => $this->summary($payment)]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function cardPaymentData(array $validated, string $fallbackEmail): array
    {
        $payer = ['email' => $validated['payer']['email'] ?? $fallbackEmail];

        if (! empty($validated['payer']['identification']['number'])) {
            $payer['identification'] = $validated['payer']['identification'];
        }

        return array_filter([
            'payment_method_id' => $validated['card']['payment_method_id'],
            'token' => $validated['card']['token'],
            'installments' => (int) $validated['card']['installments'],
            'issuer_id' => isset($validated['card']['issuer_id']) ? (int) $validated['card']['issuer_id'] : null,
            'payer' => $payer,
        ], fn ($value) => $value !== null);
    }

    /**
     * Campos que Mercado Pago exige para PSE en Colombia.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function psePaymentData(array $validated, string $fallbackEmail, ?string $ipAddress): array
    {
        $payer = $validated['payer'];

        return [
            'payment_method_id' => 'pse',
            // Al terminar en el banco, PSE devuelve al comprador aquí. Solo
            // se acepta una URL del frontend configurado (sin open redirect).
            'callback_url' => $this->frontendRedirect->allowed($validated['return_url'] ?? null) ?? route('payment.return'),
            'payer' => [
                'email' => $payer['email'] ?? $fallbackEmail,
                'entity_type' => $validated['pse']['entity_type'],
                'first_name' => $payer['first_name'],
                'last_name' => $payer['last_name'],
                'identification' => $payer['identification'],
                'phone' => $payer['phone'],
                'address' => $payer['address'],
            ],
            'additional_info' => ['ip_address' => $ipAddress],
            'transaction_details' => ['financial_institution' => $validated['pse']['financial_institution']],
        ];
    }

    /**
     * Solo lo que el frontend necesita; nunca el pago completo de Mercado
     * Pago (trae datos del pagador y de la tarjeta).
     *
     * @param  array<string, mixed>  $payment
     * @return array{id: string|null, status: string|null, status_detail: string|null, payment_method_id: string|null, external_reference: string|null, redirect_url: string|null}
     */
    private function summary(array $payment): array
    {
        return [
            'id' => isset($payment['id']) ? (string) $payment['id'] : null,
            'status' => $payment['status'] ?? null,
            'status_detail' => $payment['status_detail'] ?? null,
            'payment_method_id' => $payment['payment_method_id'] ?? null,
            'external_reference' => $payment['external_reference'] ?? null,
            // PSE: URL del banco a la que hay que redirigir al comprador.
            'redirect_url' => data_get($payment, 'transaction_details.external_resource_url'),
        ];
    }
}
