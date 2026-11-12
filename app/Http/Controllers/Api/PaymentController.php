<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FrontendRedirect;
use App\Services\PaymentService;
use App\Services\WompiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pago con Wompi (Web Checkout) de una referencia ya creada por
 * POST /api/orders, POST /api/checkout o POST /api/subscriptions/checkout:
 *
 * 1. POST /api/payments devuelve `payment_url`, el enlace firmado del Web
 *    Checkout. El frontend redirige ahí al comprador, que paga con tarjeta,
 *    PSE, Nequi, Bancolombia... en la página de Wompi.
 * 2. Wompi lo devuelve a `return_url?id={transacción}`. El frontend consulta
 *    GET /api/payments/{id} para mostrar el resultado.
 *
 * El monto siempre se calcula aquí desde la base de datos.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly WompiService $wompi,
        private readonly PaymentService $payments,
        private readonly FrontendRedirect $frontendRedirect,
    ) {}

    /**
     * Lo que el frontend necesita saber de la pasarela. La llave pública es
     * pública por diseño; ningún secreto sale de aquí.
     */
    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'enabled' => $this->wompi->enabled(),
            'provider' => 'wompi',
            'public_key' => $this->wompi->enabled() ? $this->wompi->publicKey() : null,
        ]]);
    }

    /**
     * Enlace de pago de una referencia del usuario. `return_url` solo se
     * acepta si es del frontend configurado (sin open redirect); si no, se
     * usa la página de retorno de este backend.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->wompi->enabled()) {
            abort(503, 'La pasarela de pagos no está configurada todavía.');
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'return_url' => ['nullable', 'string', 'url', 'max:2048'],
        ]);

        $paymentUrl = $this->payments->checkoutUrlFor(
            $request->user(),
            $validated['reference'],
            $this->frontendRedirect->allowed($validated['return_url'] ?? null) ?? route('payment.return'),
        );

        abort_if($paymentUrl === null, 404, 'No encontramos un pago pendiente con esa referencia.');

        return response()->json(['data' => [
            'reference' => $validated['reference'],
            'payment_url' => $paymentUrl,
        ]], 201);
    }

    /**
     * Estado de una transacción de Wompi del usuario (al volver del Web
     * Checkout llega como ?id=...). Se consulta directamente a Wompi y se
     * aplica, por si el webhook aún no llegó. 404 si no es del usuario.
     */
    public function show(Request $request, string $transactionId): JsonResponse
    {
        if (! $this->wompi->enabled()) {
            abort(404);
        }

        try {
            $transaction = $this->wompi->findTransaction($transactionId);
        } catch (ConnectionException|RequestException $e) {
            report($e);
            abort(503, 'No pudimos consultar el pago en Wompi. Intenta de nuevo en unos segundos.');
        }

        if ($transaction === null || ! $this->payments->belongsTo($request->user(), (string) ($transaction['reference'] ?? ''))) {
            abort(404);
        }

        $this->payments->apply($transaction);

        return response()->json(['data' => $this->summary($transaction)]);
    }

    /**
     * Solo lo que el frontend necesita; nunca la transacción completa (trae
     * datos del pagador y del medio de pago).
     *
     * @param  array<string, mixed>  $transaction
     * @return array{id: string|null, status: string, wompi_status: string|null, status_message: string|null, payment_method_type: string|null, reference: string|null}
     */
    private function summary(array $transaction): array
    {
        return [
            'id' => isset($transaction['id']) ? (string) $transaction['id'] : null,
            'status' => $this->wompi->internalStatus($transaction['status'] ?? null),
            'wompi_status' => $transaction['status'] ?? null,
            'status_message' => $transaction['status_message'] ?? null,
            'payment_method_type' => $transaction['payment_method_type'] ?? null,
            'reference' => $transaction['reference'] ?? null,
        ];
    }
}
