<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Ejecuta las tareas programadas desde fuera. El plan gratuito de Render no
 * corre `schedule:run`, así que un cron externo (GitHub Actions, ver
 * .github/workflows/scheduled-tasks.yml) llama a este endpoint cada 10 min
 * con `Authorization: Bearer {MERCOVA_CRON_SECRET}`.
 *
 * Las dos tareas son idempotentes, por eso se corren en cada llamada:
 * - orders:cancel-stale: cancela pedidos sin pagar vencidos y repone stock.
 * - subscriptions:reconcile-payments: concilia con Wompi pagos de planes.
 */
class ScheduledTasksController extends Controller
{
    /** @var array<int, string> */
    private const COMMANDS = ['orders:cancel-stale', 'subscriptions:reconcile-payments'];

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('mercova.cron_secret');

        // Sin secreto configurado el endpoint no existe.
        abort_if($secret === '', 404);
        abort_unless(hash_equals($secret, (string) $request->bearerToken()), 401, 'No autorizado.');

        $lock = Cache::lock('scheduled-tasks:run', 300);

        if (! $lock->get()) {
            return response()->json(['message' => 'Las tareas ya se están ejecutando.'], 409);
        }

        try {
            $results = collect(self::COMMANDS)->mapWithKeys(fn (string $command) => [$command => [
                'exit_code' => Artisan::call($command),
                'output' => trim(Artisan::output()),
            ]]);
        } finally {
            $lock->release();
        }

        return response()->json(['data' => $results]);
    }
}
