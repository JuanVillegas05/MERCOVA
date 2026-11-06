<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SubscriptionCheckoutController extends Controller
{
    public function __construct(private readonly SubscriptionCheckoutService $checkout) {}

    /**
     * Cotiza el cambio sin crear nada: el frontend lo usa para mostrar
     * "Pagarás $X (descontamos $Y de tu plan actual)" antes de confirmar.
     */
    public function preview(Request $request): JsonResponse
    {
        $plan = $this->resolvePlan($request);
        $quote = $this->checkout->quote($request->user(), $plan);

        return response()->json([
            'data' => [
                ...$quote,
                'plan' => $plan,
                'starts' => $this->startsLabel($quote['change_type']),
            ],
        ]);
    }

    /**
     * Inicia el pago del plan elegido y devuelve la URL de Mercado Pago. El plan
     * del usuario no cambia hasta que el webhook confirme el pago.
     */
    public function store(Request $request): JsonResponse
    {
        $plan = $this->resolvePlan($request);
        $validated = $request->validate([
            'return_url' => ['nullable', 'string', 'url', 'max:2048'],
        ]);

        $result = $this->checkout->checkout($request->user(), $plan, $validated['return_url'] ?? null);

        return response()->json([
            'message' => 'Te estamos llevando a la pasarela de pagos.',
            'data' => [
                'payment' => $result['payment'],
                'payment_url' => $result['payment_url'],
                'starts' => $this->startsLabel($result['payment']->change_type),
            ],
        ], 201);
    }

    /**
     * Historial de pagos de planes del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->subscriptionPayments()->with('plan')->latest('id')->get(),
        ]);
    }

    /**
     * Estado de un pago: el frontend lo consulta al volver de Mercado Pago hasta
     * que deje de estar "pending". 404 si no es del usuario, para no
     * revelar que existe.
     *
     * Si sigue pendiente, le pregunta a Mercado Pago directamente (como mucho una
     * vez cada 10 s por pago): así el plan se activa aunque el webhook se
     * demore o se pierda.
     */
    public function show(Request $request, SubscriptionPayment $subscriptionPayment): JsonResponse
    {
        if ($subscriptionPayment->user_id !== $request->user()->id) {
            abort(404);
        }

        if (
            $subscriptionPayment->status === SubscriptionPayment::STATUS_PENDING
            && Cache::add("subscription-payment-reconcile:{$subscriptionPayment->id}", true, 10)
        ) {
            $subscriptionPayment = $this->checkout->reconcile($subscriptionPayment);
        }

        return response()->json([
            'data' => $subscriptionPayment->load(['plan', 'subscription']),
        ]);
    }

    private function resolvePlan(Request $request): MembershipPlan
    {
        $validated = $request->validate([
            'membership_plan_id' => [
                'required',
                'integer',
                Rule::exists('membership_plans', 'id')->where('is_active', true),
            ],
        ]);

        return MembershipPlan::findOrFail($validated['membership_plan_id']);
    }

    /**
     * "on_payment": el plan arranca al confirmarse el pago.
     * "at_period_end": arranca cuando vence el plan vigente.
     */
    private function startsLabel(string $changeType): string
    {
        return SubscriptionPayment::changeStartsImmediately($changeType) ? 'on_payment' : 'at_period_end';
    }
}
