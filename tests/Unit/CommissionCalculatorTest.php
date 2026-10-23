<?php

namespace Tests\Unit;

use App\Services\CommissionCalculator;
use Tests\TestCase;

class CommissionCalculatorTest extends TestCase
{
    public function test_default_rate_is_six_percent(): void
    {
        $this->assertSame(0.06, (new CommissionCalculator)->rate());
    }

    public function test_gross_from_net_matches_specification_example(): void
    {
        $calculator = new CommissionCalculator;

        $this->assertEqualsWithDelta(10638.30, $calculator->grossFromNet(10000), 0.01);
    }

    public function test_net_from_gross_and_commission_amount_add_up_to_gross(): void
    {
        $calculator = new CommissionCalculator;

        $net = $calculator->netFromGross(10638.30);
        $commission = $calculator->commissionAmount(10638.30);

        $this->assertEqualsWithDelta(10638.30, $net + $commission, 0.02);
    }

    public function test_breakdown_from_net_returns_consistent_values(): void
    {
        $calculator = new CommissionCalculator;

        $breakdown = $calculator->breakdownFromNet(10000);

        $this->assertSame(10000.0, $breakdown['net_amount']);
        $this->assertEqualsWithDelta(638.30, $breakdown['commission_amount'], 0.01);
        $this->assertEqualsWithDelta(10638.30, $breakdown['gross_price'], 0.01);
    }

    public function test_resolve_breakdown_prefers_desired_net_price_when_both_are_given(): void
    {
        $calculator = new CommissionCalculator;

        $breakdown = $calculator->resolveBreakdown(999999, 10000);

        $this->assertEqualsWithDelta(10000.0, $breakdown['net_amount'], 0.01);
    }

    public function test_resolve_breakdown_falls_back_to_price_when_no_net_price_given(): void
    {
        $calculator = new CommissionCalculator;

        $breakdown = $calculator->resolveBreakdown(10638.30, null);

        $this->assertEqualsWithDelta(10000.0, $breakdown['net_amount'], 0.01);
    }

    public function test_resolve_breakdown_returns_null_when_neither_is_given(): void
    {
        $calculator = new CommissionCalculator;

        $this->assertNull($calculator->resolveBreakdown(null, null));
        $this->assertNull($calculator->resolveBreakdown(0, 0));
    }
}
