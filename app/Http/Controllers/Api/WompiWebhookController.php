<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WompiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WompiWebhookController extends Controller
{
    public function __construct(private readonly WompiService $wompi) {}

    /**
     * Wompi llama este endpoint (configurado en https://comercios.wompi.co,
     * sección Eventos) cada vez que una transacción cambia de estado. Es
     * público (sin auth:sanctum) porque Wompi no manda un token Bearer de
     * esta app: en su lugar firma el payload con WOMPI_EVENTS_SECRET y esa
     * firma es lo único que nos deja confiar en el aviso.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        if (! $this->wompi->verifyEventSignature($payload)) {
            Log::warning('Webhook de Wompi con firma inválida, ignorado.', ['payload' => $payload]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $transaction = (array) data_get($payload, 'data.transaction');
        $reference = data_get($transaction, 'reference');
        $status = Str::upper((string) data_get($transaction, 'status'));
        $transactionId = data_get($transaction, 'id');

        if (! $reference) {
            return response()->json(['message' => 'Evento sin referencia, ignorado.']);
        }

        $orders = Order::query()->where('payment_reference', $reference)->get();

        if ($orders->isEmpty()) {
            Log::warning("Webhook de Wompi para una referencia que no existe: {$reference}");

            return response()->json(['message' => 'Referencia no encontrada.']);
        }

        $paymentStatus = match ($status) {
            'APPROVED' => 'approved',
            'DECLINED' => 'declined',
            'VOIDED' => 'voided',
            'ERROR' => 'error',
            default => 'pending',
        };

        foreach ($orders as $order) {
            // Un pedido ya aprobado nunca se degrada a un estado posterior
            // del mismo evento reenviado (Wompi puede reintentar webhooks).
            if ($order->payment_status === 'approved') {
                continue;
            }

            $order->update([
                'payment_status' => $paymentStatus,
                'payment_transaction_id' => $transactionId,
                'paid_at' => $paymentStatus === 'approved' ? now() : null,
                // El pago aprobado mueve el pedido a "processing" (el vendedor
                // ya puede alistar el envío); un rechazo no cambia el estado
                // del pedido solo, queda pendiente para que el comprador
                // reintente el pago desde /api/orders/{order}/payment.
                'status' => $paymentStatus === 'approved' && $order->status === 'pending'
                    ? 'processing'
                    : $order->status,
            ]);
        }

        return response()->json(['message' => 'Evento procesado.']);
    }
}
