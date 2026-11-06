<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('plan.feature:advanced_stats'). Las
 * funcionalidades válidas están en MembershipPlan::FEATURE_COLUMNS.
 *
 * A diferencia de plan.quota, aquí no hay "fallo abierto": si el usuario no
 * tiene plan con la funcionalidad, se bloquea. Los administradores pasan.
 */
class EnsurePlanFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user?->role === 'admin') {
            return $next($request);
        }

        $plan = $user?->currentPlan();

        if ($plan === null || ! $plan->hasFeature($feature)) {
            return response()->json([
                'message' => 'Esta funcionalidad no está incluida en tu plan. Mejora tu plan para acceder.',
                'code' => 'plan_feature_required',
                'feature' => $feature,
                'plan' => $plan?->slug,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
