<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Por defecto genera un plan pago intermedio (Pro). Usar free() para el plan
 * gratuito por defecto y premium() para el plan sin límites.
 *
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'tier' => 1,
            'description' => fake()->sentence(),
            'price' => 29900,
            'billing_period' => 'mensual',
            'max_listings' => 30,
            'monthly_listing_limit' => 30,
            'monthly_request_limit' => null,
            'featured_listings_limit' => 5,
            'commission_rate' => null,
            'has_verified_badge' => true,
            'has_advanced_stats' => true,
            'has_realtime_analytics' => false,
            'features' => [],
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => 0,
            'price' => 0,
            'max_listings' => 5,
            'monthly_listing_limit' => 5,
            'monthly_request_limit' => 10,
            'featured_listings_limit' => 0,
            'has_verified_badge' => false,
            'has_advanced_stats' => false,
            'is_default' => true,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => 2,
            'price' => 59900,
            'max_listings' => null,
            'monthly_listing_limit' => null,
            'monthly_request_limit' => null,
            'featured_listings_limit' => null,
            'commission_rate' => 0.04,
            'has_realtime_analytics' => true,
        ]);
    }
}
