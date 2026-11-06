<?php

namespace App\Models;

use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use HasFactory;

    /**
     * Cupos mensuales que hace cumplir el middleware "plan.quota:<cupo>".
     *
     * @var array<string, string>
     */
    public const QUOTA_COLUMNS = [
        'listings' => 'monthly_listing_limit',
        'requests' => 'monthly_request_limit',
    ];

    /**
     * Funcionalidades que se pueden exigir con el middleware "plan.feature:<funcionalidad>".
     *
     * @var array<string, string>
     */
    public const FEATURE_COLUMNS = [
        'verified_badge' => 'has_verified_badge',
        'advanced_stats' => 'has_advanced_stats',
        'realtime_analytics' => 'has_realtime_analytics',
    ];

    protected $fillable = [
        'name',
        'slug',
        'tier',
        'description',
        'price',
        'billing_period',
        'max_listings',
        'monthly_listing_limit',
        'monthly_request_limit',
        'featured_listings_limit',
        'commission_rate',
        'has_verified_badge',
        'has_advanced_stats',
        'has_realtime_analytics',
        'features',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tier' => 'integer',
        'max_listings' => 'integer',
        'monthly_listing_limit' => 'integer',
        'monthly_request_limit' => 'integer',
        'featured_listings_limit' => 'integer',
        'commission_rate' => 'decimal:4',
        'has_verified_badge' => 'boolean',
        'has_advanced_stats' => 'boolean',
        'has_realtime_analytics' => 'boolean',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Plan que recibe todo usuario nuevo y al que vuelve cuando su
     * suscripción paga vence o la cancela (el plan gratuito).
     */
    public static function defaultPlan(): ?self
    {
        return static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('tier')
            ->first();
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    /**
     * Límite mensual del cupo pedido; null = ilimitado.
     */
    public function limitFor(string $quota): ?int
    {
        if (! array_key_exists($quota, self::QUOTA_COLUMNS)) {
            throw new InvalidArgumentException("Cupo de plan desconocido: {$quota}.");
        }

        return $this->{self::QUOTA_COLUMNS[$quota]};
    }

    public function hasFeature(string $feature): bool
    {
        if (! array_key_exists($feature, self::FEATURE_COLUMNS)) {
            throw new InvalidArgumentException("Funcionalidad de plan desconocida: {$feature}.");
        }

        return (bool) $this->{self::FEATURE_COLUMNS[$feature]};
    }
}
