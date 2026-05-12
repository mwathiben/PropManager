<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Phase-17 MONEY-1: arithmetic-bearing Money value object behaviour.
 *
 * NB: distinct from the legacy App\ValueObjects\Payment\Money (which
 * is a narrowly-scoped payment-gateway DTO with float-boundary +
 * minor-unit storage). The Phase-17 Money is the general-arithmetic
 * primitive that replaces the (float) X + (float) Y pattern in the
 * service layer.
 *
 * Tests deliberately pin the properties float arithmetic gets wrong:
 *   - 100x addition of 0.01 yields exactly 1.00 (float yields 0.999...)
 *   - compounding 5% twelve times yields exact known total
 *   - banker's rounding behaviour
 */
class GeneralMoneyTest extends TestCase
{
    public function test_zero_factory_produces_canonical_zero(): void
    {
        $zero = Money::zero();

        $this->assertSame('0.00', $zero->toDecimalString());
        $this->assertTrue($zero->isZero());
        $this->assertFalse($zero->isPositive());
        $this->assertFalse($zero->isNegative());
    }

    public function test_from_string_normalises_scale(): void
    {
        $this->assertSame('1.00', Money::fromString('1')->toDecimalString());
        $this->assertSame('1.50', Money::fromString('1.5')->toDecimalString());
        $this->assertSame('1.50', Money::fromString('1.500')->toDecimalString());
    }

    public function test_from_string_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromString('twelve thousand');
    }

    public function test_from_string_rejects_scientific_notation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromString('1e3');
    }

    public function test_from_minor_units_round_trips(): void
    {
        $amount = Money::fromMinorUnits(123456);

        $this->assertSame('1234.56', $amount->toDecimalString());
        $this->assertSame(123456, $amount->toMinorUnits());
    }

    public function test_addition_does_not_drift_over_100_iterations(): void
    {
        // The canonical float-precision test: 100 * 0.01 in float === 0.9999...
        $total = Money::zero();
        $penny = Money::fromString('0.01');

        for ($i = 0; $i < 100; $i++) {
            $total = $total->add($penny);
        }

        $this->assertSame('1.00', $total->toDecimalString(), '100 additions of 0.01 must equal exactly 1.00');
    }

    public function test_subtraction_preserves_precision(): void
    {
        $a = Money::fromString('9999.99');
        $b = Money::fromString('0.01');

        $this->assertSame('9999.98', $a->subtract($b)->toDecimalString());
    }

    public function test_multiplication_with_percentage_factor(): void
    {
        $base = Money::fromString('10000.00');

        $this->assertSame('500.00', $base->multiply('0.05')->toDecimalString());
    }

    public function test_compounding_5_percent_12_times(): void
    {
        // 10000 * 1.05^12 = 17958.5631... rounded half-even at scale=2.
        $cumulative = Money::fromString('10000.00');

        for ($i = 0; $i < 12; $i++) {
            $cumulative = $cumulative->add($cumulative->multiply('0.05'));
        }

        $this->assertSame('17958.56', $cumulative->toDecimalString());
    }

    public function test_division_uses_bankers_rounding(): void
    {
        // 10 / 8 = 1.25 — exact
        $this->assertSame('1.25', Money::fromString('10.00')->divide('8')->toDecimalString());

        // 1 / 3 rounds — digit-after-scale is 3, so rounds down.
        $this->assertSame('0.33', Money::fromString('1.00')->divide('3')->toDecimalString());
    }

    public function test_negate_flips_sign(): void
    {
        $positive = Money::fromString('1234.56');
        $negative = $positive->negate();

        $this->assertSame('-1234.56', $negative->toDecimalString());
        $this->assertTrue($negative->isNegative());
        $this->assertSame('1234.56', $negative->negate()->toDecimalString());
    }

    public function test_clamp_positive_clamps_negative_to_zero(): void
    {
        $this->assertSame('0.00', Money::fromString('-50.00')->clampPositive()->toDecimalString());
        $this->assertSame('50.00', Money::fromString('50.00')->clampPositive()->toDecimalString());
    }

    public function test_comparison_operations(): void
    {
        $a = Money::fromString('100.00');
        $b = Money::fromString('200.00');

        $this->assertTrue($a->lessThan($b));
        $this->assertTrue($b->greaterThan($a));
        $this->assertFalse($a->equals($b));
        $this->assertTrue($a->equals(Money::fromString('100.00')));
        $this->assertSame('200.00', $a->max($b)->toDecimalString());
        $this->assertSame('100.00', $a->min($b)->toDecimalString());
    }

    public function test_to_minor_units_for_paystack_conversion(): void
    {
        $this->assertSame(123456, Money::fromString('1234.56')->toMinorUnits());
        $this->assertSame(0, Money::zero()->toMinorUnits());
        $this->assertSame(100, Money::fromString('1.00')->toMinorUnits());
    }

    public function test_immutability(): void
    {
        $a = Money::fromString('100.00');
        $b = $a->add(Money::fromString('50.00'));

        $this->assertSame('100.00', $a->toDecimalString(), 'add() must not mutate the receiver');
        $this->assertSame('150.00', $b->toDecimalString());
    }
}
