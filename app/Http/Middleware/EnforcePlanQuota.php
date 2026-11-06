<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('plan.quota:listings') o 'plan.quota:requests'.
 *
 * Reserva una unidad del cupo mensual del plan ANTES de ejecutar la acción
 * y la devuelve si la acción no terminó con éxito (validación fallida, sin
 * stock, etc.), así solo cuentan las publicaciones/solicitudes realmente
 * creadas. Los administradores y los planes ilimitados no consumen cupo.
 */
class EnforcePlanQuota
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $quota): Response
    {
        $user = $request->user();
        $plan = $user?->currentPlan();
        $limit = $plan?->limitFor($quota);

        if ($user === null || $user->role === 'admin' || $limit === null) {
            return $next($request);
        }

        if (! $this->subscriptions->reserveQuota($user, $quota, $limit)) {
            $noun = $quota === 'listings' ? 'publicaciones' : 'solicitudes';

            return response()->json([
                'message' => "Alcanzaste el límite de {$limit} {$noun} al mes de tu plan {$plan->name}. Mejora tu plan para continuar.",
                'code' => 'plan_limit_reached',
                'quota' => $quota,
                'limit' => $limit,
                'plan' => $plan->slug,
            ], Response::HTTP_FORBIDDEN);
        }

        // Las excepciones del controlador (abort, validación) ya llegan aquí
        // convertidas en respuesta por el pipeline del router.
        $response = $next($request);

        if (! $response->isSuccessful()) {
            $this->subscriptions->releaseQuota($user, $quota);
        }

        return $response;
    }
}
