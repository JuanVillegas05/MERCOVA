<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MercadoPagoPaymentService;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly MercadoPagoPaymentService $payments,
    ) {}

    /**
     * Mercado Pago llama este endpoint (Webhooks en el panel de la app, o el
     * `notification_url` de cada pago) cuando un pago cambia de estado. Es
     * público (sin auth:sanctum) porque Mercado Pago no manda un token
     * nuestro. Dos defensas:
     *
     * 1. El header x-signature (HMAC con MERCADOPAGO_WEBHOOK_SECRET) se valida
     *    antes de hacer nada.
     * 2. El aviso solo trae el id del pago: su estado y monto se consultan a
     *    la API con nuestro access token, nunca se toman del cuerpo.
     *
     * Responde 200 a todo aviso válido aunque no aplique, para que Mercado
     * Pago no lo reintente; un error al consultar la API devuelve 500 y
     * Mercado Pago lo reintenta más tarde.
     */
    public function handle(Request $request): JsonResponse
    {
        // Mercado Pago manda ?data.id=...&type=payment. PHP convierte los
        // puntos de los parámetros de la URL en "_", de ahí "data_id".
        $dataId = (string) ($request->query('data_id') ?? $request->input('data.id') ?? '');
        $type = $request->query('type') ?? $request->input('type');

        if (! $this->mercadoPago->hasValidWebhookSignature(
            $request->header('x-signature'),
            $request->header('x-request-id'),
            $dataId !== '' ? $dataId : null,
        )) {
            Log::warning('Webhook de Mercado Pago con firma inválida o sin MERCADOPAGO_WEBHOOK_SECRET, ignorado.', [
                'data_id' => $dataId,
                'request_id' => $request->header('x-request-id'),
            ]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        if ($type !== 'payment' || $dataId === '') {
            return response()->json(['message' => 'Evento ignorado.']);
        }

        $payment = $this->mercadoPago->findPayment($dataId);

        if ($payment === null) {
            Log::warning("Webhook de Mercado Pago para un pago que no existe: {$dataId}");

            return response()->json(['message' => 'Pago no encontrado.']);
        }

        $this->payments->apply($payment);

        return response()->json(['message' => 'Evento procesado.']);
    }
}
