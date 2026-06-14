<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ManagementFee;

use App\Models\PropertyOwner;
use App\Services\ManagementFee\FeePeriodContext;
use App\Services\ManagementFee\ManagementFeeCalculator;
use Tests\TestCase;

/**
 * The management fee a manager earns on an owner's portfolio for a period.
 *
 * Two shapes the manager negotiates per relationship:
 *  - percentage of a base (collected / billed / scheduled) — who carries the
 *    arrears risk is exactly the choice of base;
 *  - flat — either a fixed amount for the whole period, or per unit weighted by
 *    how much of the period each unit was actually occupied.
 */
class ManagementFeeCalculatorTest extends TestCase
{
    private ManagementFeeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ManagementFeeCalculator;
    }

    /** @param array<string, mixed> $attributes */
    private function relationship(array $attributes): PropertyOwner
    {
        $relationship = new PropertyOwner;

        foreach ($attributes as $key => $value) {
            $relationship->setAttribute($key, $value);
        }

        return $relationship;
    }

    public function test_percentage_on_collected_is_the_default_base(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 10,
            'management_fee_base' => 'collected',
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 80000, billed: 100000, scheduled: 100000),
        );

        $this->assertSame(8000.0, $fee);
    }

    public function test_percentage_on_billed_charges_the_full_rent_roll(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 10,
            'management_fee_base' => 'billed',
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 80000, billed: 100000),
        );

        $this->assertSame(10000.0, $fee);
    }

    public function test_percentage_on_scheduled_uses_contracted_rent(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 12.5,
            'management_fee_base' => 'scheduled',
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 50000, scheduled: 100000),
        );

        $this->assertSame(12500.0, $fee);
    }

    public function test_percentage_falls_back_to_collected_when_base_is_null(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 10,
            'management_fee_base' => null,
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 70000, billed: 90000),
        );

        $this->assertSame(7000.0, $fee);
    }

    public function test_percentage_is_clamped_at_one_hundred_percent(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 150,
            'management_fee_base' => 'collected',
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 5000),
        );

        $this->assertSame(5000.0, $fee);
    }

    public function test_flat_per_period_is_a_fixed_amount(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'flat',
            'management_fee_value' => 5000,
            'management_fee_flat_cadence' => 'per_period',
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 80000, occupancyWeightedUnits: 9),
        );

        $this->assertSame(5000.0, $fee);
    }

    public function test_flat_per_unit_is_weighted_by_period_occupancy(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'flat',
            'management_fee_value' => 1000,
            'management_fee_flat_cadence' => 'per_unit',
        ]);

        // 3 units occupied the whole period + 1 unit occupied half of it = 3.5.
        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(occupancyWeightedUnits: 3.5),
        );

        $this->assertSame(3500.0, $fee);
    }

    public function test_flat_defaults_to_per_period_when_cadence_is_null(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'flat',
            'management_fee_value' => 4200,
            'management_fee_flat_cadence' => null,
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(occupancyWeightedUnits: 10),
        );

        $this->assertSame(4200.0, $fee);
    }

    public function test_type_none_yields_zero(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'none',
            'management_fee_value' => 9999,
        ]);

        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 80000),
        );

        $this->assertSame(0.0, $fee);
    }

    public function test_result_is_rounded_to_two_decimals(): void
    {
        $relationship = $this->relationship([
            'management_fee_type' => 'percentage',
            'management_fee_value' => 7.5,
            'management_fee_base' => 'collected',
        ]);

        // 7.5% of 1333.33 = 99.99975 -> 100.00
        $fee = $this->calculator->calculate(
            $relationship,
            new FeePeriodContext(collected: 1333.33),
        );

        $this->assertSame(100.0, $fee);
    }
}
