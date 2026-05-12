<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\LateFeePolicy;
use App\Services\LateFeeService;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-17 Phase 1 coverage:
 *   MONEY-1: Money value object is the canonical money primitive
 *   MONEY-2: Invoice::recalculateLateFees + getOutstandingMoney use Money
 *   MONEY-2: LateFeeService::isEligibleForLateFee + applyLateFee use Money
 *   MONEY-3: Currency::toMinorUnitsFromMoney(Money) preserves precision
 *   MONEY-3: LateFeePolicy::calculateFeeMoney compounding is exact
 */
class Phase17MoneyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_invoice_get_outstanding_money_returns_money_value_object(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $tenantSetup = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $invoice = Invoice::factory()->create([
            'landlord_id' => $landlord->id,
            'lease_id' => $tenantSetup['lease']->id,
            'total_due' => '12345.67',
            'amount_paid' => '12000.00',
        ]);

        $outstanding = $invoice->getOutstandingMoney();

        $this->assertInstanceOf(Money::class, $outstanding);
        $this->assertSame('345.67', $outstanding->toDecimalString());
    }

    public function test_invoice_outstanding_clamps_negative_at_zero(): void
    {
        // Over-payment scenario: amount_paid > total_due. The outstanding
        // value must clamp at zero (the over-paid amount surfaces via
        // wallet_balance, not as a negative receivable).
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'total_due' => '1000.00',
            'amount_paid' => '1500.00',
        ]);

        $this->assertSame('0.00', $invoice->getOutstandingMoney()->toDecimalString());
    }

    public function test_late_fee_policy_compounding_does_not_drift_over_12_months(): void
    {
        // Phase-17 MONEY-2 + MONEY-4. The float-arithmetic implementation
        // drifts vs. the exact compound formula 10000 * 1.05^12 = 17958.5631.
        // Money + banker's rounding lands at 17958.56 exactly.
        $setup = $this->createLandlordWithFullSetup();
        $policy = LateFeePolicy::create([
            'landlord_id' => $setup['landlord']->id,
            'name' => 'monthly compounding 5%',
            'grace_period_days' => 0,
            'fee_type' => 'percentage',
            'fee_percentage' => '5.00',
            'fee_amount' => 0,
            'is_compounding' => true,
            'compounding_frequency' => 'monthly',
            'max_fee_cap' => null,
            'is_active' => true,
            'priority' => 1,
        ]);

        $base = Money::fromString('10000.00');
        $existing = Money::zero();

        for ($i = 0; $i < 12; $i++) {
            $fee = $policy->calculateFeeMoney($base, $existing);
            $existing = $existing->add($fee);
        }

        // 10000 * (1.05^12 - 1) = 7958.5631... — banker's-rounded sum
        // accumulates the fee total exactly.
        $this->assertSame('7958.56', $existing->toDecimalString());
    }

    public function test_late_fee_policy_fee_cap_truncates_fee(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $policy = LateFeePolicy::create([
            'landlord_id' => $setup['landlord']->id,
            'name' => 'capped',
            'grace_period_days' => 0,
            'fee_type' => 'percentage',
            'fee_percentage' => '10.00',
            'fee_amount' => 0,
            'is_compounding' => false,
            'compounding_frequency' => null,
            'max_fee_cap' => '500.00',
            'is_active' => true,
            'priority' => 1,
        ]);

        // First fee at 10% of 10000 = 1000, but cap is 500 — should be 500.
        $fee = $policy->calculateFeeMoney(
            Money::fromString('10000.00'),
            Money::zero(),
        );
        $this->assertSame('500.00', $fee->toDecimalString());

        // Subsequent fee with existingLateFees=500 and cap=500 should be 0.
        $fee = $policy->calculateFeeMoney(
            Money::fromString('10000.00'),
            Money::fromString('500.00'),
        );
        $this->assertSame('0.00', $fee->toDecimalString());
    }

    public function test_invoice_recalculate_late_fees_uses_money(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'rent_due' => '10000.00',
            'water_due' => '500.00',
            'arrears' => '0.00',
            'wallet_applied' => '0.00',
            'late_fees_total' => '0.00',
            'late_fees_waived' => '0.00',
            'total_due' => '10500.00',
            'amount_paid' => '0.00',
        ]);

        // Recalc with no late fees — total_due unchanged.
        $invoice->recalculateLateFees();
        $invoice->refresh();
        $this->assertSame('10500.00', (string) $invoice->total_due);
    }

    public function test_currency_to_minor_units_from_money_preserves_precision(): void
    {
        $currency = \App\Enums\Currency::KES;

        $this->assertSame(123456, $currency->toMinorUnitsFromMoney(Money::fromString('1234.56')));
        $this->assertSame(100000, $currency->toMinorUnitsFromMoney(Money::fromString('1000.00')));
        $this->assertSame(0, $currency->toMinorUnitsFromMoney(Money::zero()));
    }
}
