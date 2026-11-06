<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cobro de planes de pago con Mercado Pago.
 *
 * Flujo:
 * 1. quote() decide qué tipo de cambio es (alta, upgrade, renovación o
 *    downgrade) y cuánto cobrar.
 * 2. checkout() registra un SubscriptionPayment "pending" con una referencia
 *    SUB-... y devuelve la URL de Checkout Pro. El frontend también puede
 *    cobrar esa referencia en su propio formulario (POST /api/payments).
 *    La suscripción NO cambia aún.
 * 3. Mercado Pago avisa a POST /api/webhooks/mercadopago; si la referencia es
 *    SUB-..., MercadoPagoPaymentService delega en handlePaymentEvent(), que
 *    activa (o programa) la suscripción solo si el pago quedó aprobado y el
 *    monto cobrado coincide con el registrado.
 *
 * No hay cobros recurrentes: cada periodo es un pago independiente
 * (renovación) y un plan que nadie renueva simplemente vence y el usuario
 * vuelve al plan gratuito.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly SubscriptionService $subscriptions,
        private readonly FrontendRedirect $frontendRedirect,
    ) {}

    /**
     * Valida el cambio de plan y calcula el monto ANTES de ir a la pasarela.
     *
     * @return array{change_type: string, amount: float, credit_amount: float}
     */
    public function quote(User $user, MembershipPlan $plan): array
    {
        if ($plan->isFree()) {
            abort(422, 'El plan gratuito no requiere pago. Usa PUT /api/me/subscription para volver a él.');
        }

        $scheduled = $this->subscriptions->scheduledSubscription($user);

        if ($scheduled) {
            abort(422, "Ya tienes programado el plan \"{$scheduled->plan?->name}\" desde el {$scheduled->starts_at->format('d/m/Y')}. Podrás hacer otro cambio cuando empiece.");
        }

        $price = (float) $plan->price;
        $current = $this->subscriptions->currentPaidSubscription($user);

        if ($current === null) {
            return $this->quoteOf(SubscriptionPayment::CHANGE_NEW, $price);
        }

        if ($current->membership_plan_id === $plan->id) {
            return $this->quoteOf(SubscriptionPayment::CHANGE_RENEWAL, $price);
        }

        if (! $this->ranksAbove($plan, $current->plan)) {
            // Bajar de plan no reembolsa nada: el plan inferior empieza
            // cuando termina el periodo que el usuario ya pagó.
            return $this->quoteOf(SubscriptionPayment::CHANGE_DOWNGRADE, $price);
        }

        $credit = min($price, $this->unusedCredit($current));
        $amount = round($price - $credit);

        if ($amount < 1) {
            abort(422, 'No se pudo calcular el valor del cambio de plan. Contacta a soporte.');
        }

        return $this->quoteOf(SubscriptionPayment::CHANGE_UPGRADE, $amount, $credit);
    }

    /**
     * Crea (o reutiliza) el intento de pago y devuelve la URL de Checkout Pro.
     *
     * @return array{payment: SubscriptionPayment, payment_url: string}
     */
    public function checkout(User $user, MembershipPlan $plan, ?string $returnUrl = null): array
    {
        if (! $this->mercadoPago->enabled()) {
            abort(503, 'La pasarela de pagos no está configurada todavía.');
        }

        $quote = $this->quote($user, $plan);

        // Si el usuario vuelve a pulsar "Pagar" (cerró la pestaña de pago,
        // doble clic...), se reutiliza el intento pendiente reciente en vez de
        // crear referencias nuevas que podría terminar pagando dos veces.
        $payment = SubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('membership_plan_id', $plan->id)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->where('change_type', $quote['change_type'])
            ->where('amount', $quote['amount'])
            ->where('created_at', '>=', now()->subHour())
            ->latest('id')
            ->first();

        $payment ??= SubscriptionPayment::create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'change_type' => $quote['change_type'],
            'reference' => $this->mercadoPago->generateReference('SUB'),
            'amount' => $quote['amount'],
            'credit_amount' => $quote['credit_amount'],
            'currency' => 'COP',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);

        try {
            $paymentUrl = $this->mercadoPago->createPreference(
                reference: $payment->reference,
                title: 'Plan '.$plan->name,
                amount: (float) $payment->amount,
                // Mercado Pago agrega ?payment_id=...&status=... al volver. Solo
                // se acepta una URL del frontend configurado; si no, la propia.
                returnUrl: $this->frontendRedirect->allowed($returnUrl) ?? route('payment.return'),
                payerEmail: $user->email,
            );
        } catch (Throwable $e) {
            // El intento queda "pending" y se reutiliza al reintentar.
            report($e);
            abort(503, 'No pudimos conectar con Mercado Pago. Intenta de nuevo en unos minutos.');
        }

        return [
            'payment' => $payment->load('plan'),
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Consulta a Mercado Pago el estado real de un pago aún abierto y lo
     * aplica. Cubre el caso de un webhook perdido o demorado: lo usa el
     * comando programado y el endpoint que el frontend consulta al volver de
     * la pasarela. Lo que responde la API (autenticada con nuestro access
     * token) es tan confiable como el propio webhook.
     */
    public function reconcile(SubscriptionPayment $payment): SubscriptionPayment
    {
        if (! $this->mercadoPago->enabled() || ! in_array($payment->status, MercadoPagoPaymentService::RETRYABLE_SUBSCRIPTION_PAYMENT_STATUSES, true)) {
            return $payment;
        }

        try {
            $attempts = collect($this->mercadoPago->paymentsByReference($payment->reference));
        } catch (Throwable $e) {
            // Mercado Pago no responde: se reintenta en la próxima pasada.
            report($e);

            return $payment;
        }

        // Un aprobado gana siempre; si no, cuenta el intento más reciente.
        $attempt = $attempts->firstWhere('status', 'approved') ?? $attempts->first();

        if ($attempt) {
            $this->handlePaymentEvent(
                $payment,
                $this->mercadoPago->internalStatus($attempt['status'] ?? null),
                isset($attempt['id']) ? (string) $attempt['id'] : null,
                isset($attempt['transaction_amount']) ? (float) $attempt['transaction_amount'] : null,
            );
        }

        return $payment->fresh();
    }

    /**
     * Aplica el resultado de un pago de Mercado Pago a un pago de plan
     * ($status ya traducido por MercadoPagoService::internalStatus()). Es
     * idempotente: Mercado Pago reintenta los webhooks y un pago ya aprobado
     * no se vuelve a aplicar ni se degrada. Un rechazo deja el pago abierto
     * a un nuevo intento (otra tarjeta, otro banco).
     */
    public function handlePaymentEvent(SubscriptionPayment $payment, string $status, ?string $transactionId, ?float $amount): void
    {
        DB::transaction(function () use ($payment, $status, $transactionId, $amount) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->find($payment->id);

            if ($payment === null || in_array($payment->status, [SubscriptionPayment::STATUS_APPROVED, SubscriptionPayment::STATUS_AMOUNT_MISMATCH], true)) {
                return;
            }

            if ($status !== SubscriptionPayment::STATUS_APPROVED) {
                if ($status !== 'refunded') {
                    $payment->update(['status' => $status, 'transaction_id' => $transactionId]);
                }

                return;
            }

            // Si el monto cobrado no coincide con lo que cotizamos, no se
            // activa nada y queda para revisión manual.
            $expectedCents = $this->mercadoPago->amountInCents((float) $payment->amount);
            $receivedCents = $amount !== null ? $this->mercadoPago->amountInCents($amount) : null;

            if ($receivedCents !== $expectedCents) {
                Log::error("Pago de suscripción {$payment->reference} aprobado por un monto distinto al esperado.", [
                    'expected_cents' => $expectedCents,
                    'received_cents' => $receivedCents,
                ]);
                $payment->update(['status' => SubscriptionPayment::STATUS_AMOUNT_MISMATCH, 'transaction_id' => $transactionId]);

                return;
            }

            $payment->update([
                'status' => SubscriptionPayment::STATUS_APPROVED,
                'transaction_id' => $transactionId,
                'paid_at' => now(),
                'subscription_id' => $this->activate($payment)?->id,
            ]);
        });
    }

    private function activate(SubscriptionPayment $payment): ?Subscription
    {
        $user = $payment->user;
        $plan = $payment->plan;

        if ($plan === null) {
            // El admin borró el plan entre el checkout y la aprobación: el
            // dinero ya se cobró, así que queda registrado para soporte.
            Log::critical("Pago de suscripción {$payment->reference} aprobado para un plan que ya no existe.");

            return null;
        }

        if ($payment->startsImmediately()) {
            return $this->subscriptions->subscribe($user, $plan);
        }

        return $this->subscriptions->schedule($user, $plan, $this->subscriptions->nextAvailableStart($user));
    }

    /**
     * Plan "superior" = mayor nivel; a igual nivel, el más caro.
     */
    private function ranksAbove(MembershipPlan $candidate, ?MembershipPlan $current): bool
    {
        if ($current === null) {
            return true;
        }

        return [$candidate->tier, (float) $candidate->price] > [$current->tier, (float) $current->price];
    }

    /**
     * Valor no consumido del periodo actual, en pesos enteros, para
     * descontarlo en un upgrade. Solo hay crédito si ese periodo se pagó de
     * verdad (un plan regalado por el admin no genera saldo a favor).
     */
    private function unusedCredit(Subscription $current): float
    {
        $paidPayment = SubscriptionPayment::query()
            ->where('subscription_id', $current->id)
            ->where('status', SubscriptionPayment::STATUS_APPROVED)
            ->first();

        if ($paidPayment === null || $current->ends_at === null) {
            return 0.0;
        }

        $periodValue = (float) $paidPayment->amount + (float) $paidPayment->credit_amount;
        $totalSeconds = $current->starts_at->diffInSeconds($current->ends_at);
        $remainingSeconds = max(0, now()->diffInSeconds($current->ends_at));

        if ($totalSeconds <= 0) {
            return 0.0;
        }

        return round($periodValue * $remainingSeconds / $totalSeconds);
    }

    /**
     * @return array{change_type: string, amount: float, credit_amount: float}
     */
    private function quoteOf(string $changeType, float $amount, float $credit = 0.0): array
    {
        return ['change_type' => $changeType, 'amount' => $amount, 'credit_amount' => $credit];
    }
}
