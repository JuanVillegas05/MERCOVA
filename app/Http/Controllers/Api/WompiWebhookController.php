<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WompiWebhookController extends Controller
{
    public function __construct(
        private readonly WompiService $wompi,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Wompi llama este endpoint ("URL de eventos" en el panel de
     * Desarrolladores) cuando una transacción cambia de estado. Es público
     * (sin auth:sanctum) porque Wompi no manda un token nuestro. Dos defensas:
     *
     * 1. El checksum del evento (con WOMPI_EVENTS_SECRET) se valida antes de
     *    hacer nada.
     * 2. El estado y el monto se vuelven a consultar a la API de Wompi por el
     *    id de la transacción; no se toman del cuerpo del evento.
     *
     * Responde 200 a todo evento válido aunque no aplique, para que Wompi no
     * lo reintente; un error al consultar la API devuelve 500 y Wompi lo
     * reintenta más tarde.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = $request->json()->all();

        if (! $this->wompi->hasValidEventSignature($event)) {
            Log::warning('Evento de Wompi con checksum inválido o sin WOMPI_EVENTS_SECRET, ignorado.', [
                'event' => $event['event'] ?? null,
                'transaction_id' => data_get($event, 'data.transaction.id'),
            ]);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }

        $transactionId = (string) data_get($event, 'data.transaction.id', '');

        if (($event['event'] ?? null) !== 'transaction.updated' || $transactionId === '') {
            return response()->json(['message' => 'Evento ignorado.']);
        }

        $transaction = $this->wompi->findTransaction($transactionId);

        if ($transaction === null) {
            Log::warning("Evento de Wompi para una transacción que no existe: {$transactionId}");

            return response()->json(['message' => 'Transacción no encontrada.']);
        }

        $this->payments->apply($transaction);

        return response()->json(['message' => 'Evento procesado.']);
    }
}
