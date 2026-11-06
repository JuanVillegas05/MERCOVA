<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'change_type' => SubscriptionPayment::CHANGE_NEW,
            'reference' => 'SUB-'.Str::upper(Str::random(12)),
            'amount' => 29900,
            'credit_amount' => 0,
            'currency' => 'COP',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ];
    }
}
