<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * Estado del plan del usuario autenticado: plan, vigencia, beneficios y
     * cuánto le queda de cada cupo este mes.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->subscriptions->summaryFor($request->user()),
        ]);
    }

    /**
     * Cambio de plan SIN pago: solo para volver al plan gratuito. Los planes
     * de pago se contratan por POST /api/subscriptions/checkout y se activan
     * únicamente cuando Mercado Pago confirma el pago.
     *
     * Si el usuario tiene un plan pago vigente, no se corta: sigue hasta su
     * fecha de vencimiento (ya está pagado) y luego pasa al gratuito.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_plan_id' => [
                'required',
                'integer',
                Rule::exists('membership_plans', 'id')->where('is_active', true),
            ],
        ]);

        $user = $request->user();
        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        if (! $plan->isFree()) {
            return response()->json([
                'message' => 'Este plan requiere pago. Contrátalo desde el checkout.',
                'code' => 'payment_required',
            ], 422);
        }

        $paidSubscription = $this->subscriptions->currentPaidSubscription($user);

        if ($paidSubscription) {
            $this->subscriptions->cancelAtPeriodEnd($paidSubscription);

            return response()->json([
                'message' => "Tu plan \"{$paidSubscription->plan->name}\" seguirá activo hasta el {$paidSubscription->ends_at->format('d/m/Y')}. Después pasarás al plan \"{$plan->name}\".",
                'data' => $this->subscriptions->summaryFor($user),
            ]);
        }

        if ($user->activeSubscription?->membership_plan_id === $plan->id) {
            abort(422, "Ya tienes activo el plan \"{$plan->name}\".");
        }

        $this->subscriptions->subscribe($user, $plan);

        return response()->json([
            'message' => "Plan \"{$plan->name}\" activado con éxito.",
            'data' => $this->subscriptions->summaryFor($user),
        ]);
    }

    /**
     * El admin asigna cualquier plan (incluso uno inactivo o privado) a un
     * usuario, por ejemplo tras confirmar un pago manual o como cortesía.
     */
    public function adminUpdate(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
        ]);

        $plan = MembershipPlan::findOrFail($validated['membership_plan_id']);

        $this->subscriptions->subscribe($user, $plan);

        return response()->json([
            'message' => "Plan \"{$plan->name}\" asignado a {$user->name}.",
            'data' => $this->subscriptions->summaryFor($user),
        ]);
    }
}
