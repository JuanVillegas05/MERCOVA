<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\PlanUsage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Punto único para todo lo que tenga que ver con el plan de un usuario:
 * suscribirlo, consultar su estado y consumir sus cupos mensuales.
 * Ningún controlador debería leer límites del plan por su cuenta.
 */
class SubscriptionService
{
    public function __construct(private readonly CommissionCalculator $commission) {}

    /**
     * Activa $plan para $user desde ya. La suscripción vigente anterior (si
     * había) queda cancelada como historial: nunca hay dos vigentes a la vez.
     */
    public function subscribe(User $user, MembershipPlan $plan): Subscription
    {
        $subscription = DB::transaction(function () use ($user, $plan) {
            // Serializa cambios de plan simultáneos del mismo usuario para
            // que no queden dos suscripciones activas.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            // Solo se cancela la vigente: un plan futuro ya pagado y
            // programado (renovación/downgrade) se conserva.
            $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('starts_at', '<=', now())
                ->update([
                    'status' => Subscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);

            return $user->subscriptions()->create([
                'membership_plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at' => $this->endsAtFor($plan),
            ]);
        });

        $user->unsetRelation('activeSubscription');

        return $subscription;
    }

    /**
     * Programa $plan para que empiece en $startsAt sin tocar la suscripción
     * vigente (renovaciones y downgrades pagados). No hace falta ningún cron:
     * el scope Subscription::current() la vuelve vigente sola cuando llega
     * su fecha y la anterior vence.
     */
    public function schedule(User $user, MembershipPlan $plan, Carbon $startsAt): Subscription
    {
        $subscription = $user->subscriptions()->create([
            'membership_plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $this->endsAtFor($plan, $startsAt),
        ]);

        $user->unsetRelation('activeSubscription');

        return $subscription;
    }

    /**
     * Suscripción vigente solo si es de un plan de pago (la gratuita no
     * cuenta para upgrades, renovaciones ni prorrateos).
     */
    public function currentPaidSubscription(User $user): ?Subscription
    {
        $subscription = $user->activeSubscription;

        return $subscription?->plan && ! $subscription->plan->isFree() ? $subscription : null;
    }

    /**
     * Próximo plan ya pagado que espera a que venza el actual, si hay.
     */
    public function scheduledSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()
            ->with('plan')
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Fecha en la que debe arrancar un plan encolado: al final de la última
     * suscripción vigente o programada, o ya mismo si no hay ninguna.
     */
    public function nextAvailableStart(User $user): Carbon
    {
        $lastEnd = $user->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->max('ends_at');

        return $lastEnd ? Carbon::parse($lastEnd) : now();
    }

    /**
     * "Volver al plan gratuito": el plan pago sigue vigente hasta su fecha
     * de vencimiento (ya está pagado) y no se renueva. Como los planes se
     * cobran periodo a periodo (sin cobros recurrentes en Wompi), nunca hay un cobro que detener:
     * esto deja constancia de la decisión para el frontend y recordatorios.
     */
    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        if ($subscription->cancelled_at === null) {
            $subscription->update(['cancelled_at' => now()]);
        }
    }

    /**
     * Se llama al registrarse (formulario o login social). Si el catálogo
     * de planes no está sembrado no hace nada: el usuario igual funciona y
     * queda sin límites hasta que exista un plan por defecto.
     */
    public function subscribeToDefaultPlan(User $user): ?Subscription
    {
        $plan = MembershipPlan::defaultPlan();

        return $plan ? $this->subscribe($user, $plan) : null;
    }

    /**
     * Intenta consumir una unidad del cupo mensual. El incremento es una
     * sola sentencia condicionada (used < limit), así dos peticiones
     * simultáneas no pueden superar el límite leyendo el mismo valor.
     */
    public function reserveQuota(User $user, string $quota, int $limit): bool
    {
        $column = $this->usageColumn($quota);
        $period = PlanUsage::currentPeriod();

        PlanUsage::query()->insertOrIgnore([
            'user_id' => $user->id,
            'period' => $period,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PlanUsage::query()
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->where($column, '<', $limit)
            ->increment($column) === 1;
    }

    /**
     * Devuelve una unidad reservada cuando la acción no llegó a completarse
     * (falló la validación, no había stock, etc.).
     */
    public function releaseQuota(User $user, string $quota): void
    {
        $column = $this->usageColumn($quota);

        PlanUsage::query()
            ->where('user_id', $user->id)
            ->where('period', PlanUsage::currentPeriod())
            ->where($column, '>', 0)
            ->decrement($column);
    }

    /**
     * Valida que $user pueda marcar una publicación más como destacada.
     * featured_listings_limit: 0 = el plan no permite destacar, null = sin límite.
     */
    public function assertCanFeatureListing(User $user): void
    {
        $plan = $user->currentPlan();

        if ($user->role === 'admin' || $plan === null || $plan->featured_listings_limit === null) {
            return;
        }

        $limit = $plan->featured_listings_limit;

        if ($limit === 0) {
            abort(403, 'Tu plan no incluye publicaciones destacadas. Mejora tu plan para destacar productos y servicios.');
        }

        $featuredCount = $user->products()->where('is_featured', true)->count()
            + $user->services()->where('is_featured', true)->count();

        if ($featuredCount >= $limit) {
            abort(403, "Alcanzaste el límite de {$limit} publicaciones destacadas de tu plan.");
        }
    }

    /**
     * Estado de la suscripción que ve el frontend tras iniciar sesión.
     *
     * @return array{
     *     status: 'active'|'default'|'none',
     *     plan: MembershipPlan|null,
     *     is_free: bool,
     *     starts_at: Carbon|null,
     *     ends_at: Carbon|null,
     *     cancels_at_period_end: bool,
     *     scheduled: array{plan: array<string, mixed>|null, starts_at: Carbon, ends_at: Carbon|null}|null,
     *     benefits: array{verified_badge: bool, advanced_stats: bool, realtime_analytics: bool, featured_listings_limit: int|null, commission_rate: float},
     *     usage: array{period: string, listings: array{used: int, limit: int|null, remaining: int|null}, requests: array{used: int, limit: int|null, remaining: int|null}}
     * }
     */
    public function summaryFor(User $user): array
    {
        $subscription = $user->activeSubscription;
        $plan = $user->currentPlan();

        $usage = PlanUsage::query()
            ->where('user_id', $user->id)
            ->where('period', PlanUsage::currentPeriod())
            ->first();

        $scheduled = $this->scheduledSubscription($user);

        return [
            'status' => match (true) {
                $subscription?->plan !== null => 'active',
                $plan !== null => 'default',
                default => 'none',
            },
            'plan' => $plan,
            'is_free' => $plan?->isFree() ?? true,
            'starts_at' => $subscription?->starts_at,
            'ends_at' => $subscription?->ends_at,
            'cancels_at_period_end' => $subscription?->cancelled_at !== null && $subscription?->ends_at !== null && $scheduled === null,
            'scheduled' => $scheduled ? [
                'plan' => $scheduled->plan?->only(['id', 'name', 'slug', 'tier']),
                'starts_at' => $scheduled->starts_at,
                'ends_at' => $scheduled->ends_at,
            ] : null,
            'benefits' => [
                'verified_badge' => $plan?->has_verified_badge ?? false,
                'advanced_stats' => $plan?->has_advanced_stats ?? false,
                'realtime_analytics' => $plan?->has_realtime_analytics ?? false,
                'featured_listings_limit' => $plan?->featured_listings_limit,
                'commission_rate' => $this->commission->forSeller($user)->rate(),
            ],
            'usage' => [
                'period' => PlanUsage::currentPeriod(),
                'listings' => $this->quotaUsage($plan, 'listings', $usage?->listings_used ?? 0),
                'requests' => $this->quotaUsage($plan, 'requests', $usage?->requests_used ?? 0),
            ],
        ];
    }

    /**
     * @return array{used: int, limit: int|null, remaining: int|null}
     */
    private function quotaUsage(?MembershipPlan $plan, string $quota, int $used): array
    {
        $limit = $plan?->limitFor($quota);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }

    /**
     * Fin del periodo pagado que empieza en $from (ahora, por defecto).
     * El plan gratuito no vence.
     */
    public function endsAtFor(MembershipPlan $plan, ?Carbon $from = null): ?Carbon
    {
        if ($plan->isFree()) {
            return null;
        }

        $from = ($from ?? now())->copy();

        return $plan->billing_period === 'anual' ? $from->addYear() : $from->addMonth();
    }

    private function usageColumn(string $quota): string
    {
        if (! array_key_exists($quota, MembershipPlan::QUOTA_COLUMNS)) {
            throw new InvalidArgumentException("Cupo de plan desconocido: {$quota}.");
        }

        return "{$quota}_used";
    }
}
