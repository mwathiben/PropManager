<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-19 Phase 2 coverage (INDEX HIGH severity):
 *   INDEX-1 (DATA-4 closure): latefees:audit-drift command exists,
 *     detects invoice.late_fees_total drift, exits FAILURE on drift,
 *     exits SUCCESS on balanced state. Same shape as Phase-17 MONEY-5
 *     payments:audit-allocations.
 */
class Phase19IndexTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_audit_late_fees_drift_passes_when_balanced(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $policy = LateFeePolicy::create([
            'landlord_id' => $setup['landlord']->id,
            'name' => 'Standard 5%',
            'calculation_method' => 'percentage',
            'percentage' => 5.00,
            'grace_period_days' => 3,
            'apply_frequency' => 'one_time',
            'is_active' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'late_fees_total' => '500.00',
        ]);

        // 500.00 active + 100.00 waived. Recorded total = active only.
        LateFee::create([
            'invoice_id' => $invoice->id,
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $setup['landlord']->id,
            'fee_amount' => '500.00',
            'cumulative_total' => '500.00',
            'applied_date' => now()->subDays(5),
            'days_overdue' => 5,
            'is_waived' => false,
        ]);

        LateFee::create([
            'invoice_id' => $invoice->id,
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $setup['landlord']->id,
            'fee_amount' => '100.00',
            'cumulative_total' => '600.00',
            'applied_date' => now()->subDays(10),
            'days_overdue' => 10,
            'is_waived' => true,
            'waived_at' => now(),
            'waiver_reason' => 'Customer complaint',
        ]);

        $exitCode = $this->artisan('latefees:audit-drift')->run();

        $this->assertSame(0, $exitCode, 'latefees:audit-drift must exit SUCCESS when balanced (active sum equals recorded).');
    }

    public function test_audit_late_fees_drift_detects_drift_and_exits_failure(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $policy = LateFeePolicy::create([
            'landlord_id' => $setup['landlord']->id,
            'name' => 'Standard 5%',
            'calculation_method' => 'percentage',
            'percentage' => 5.00,
            'grace_period_days' => 3,
            'apply_frequency' => 'one_time',
            'is_active' => true,
        ]);

        // Recorded total claims 700; actual active rows sum to 500 → 200 drift.
        $invoice = Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'late_fees_total' => '700.00',
        ]);

        LateFee::create([
            'invoice_id' => $invoice->id,
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $setup['landlord']->id,
            'fee_amount' => '500.00',
            'cumulative_total' => '500.00',
            'applied_date' => now(),
            'days_overdue' => 5,
            'is_waived' => false,
        ]);

        $exitCode = $this->artisan('latefees:audit-drift')->run();

        $this->assertSame(1, $exitCode, 'latefees:audit-drift must exit FAILURE on drift > 0.01.');
    }

    public function test_audit_late_fees_drift_ignores_invoices_with_zero_total_and_no_fees(): void
    {
        // Sanity: an invoice with late_fees_total=0 and no LateFee rows
        // is balanced (0 == 0) and must not trigger.
        $setup = $this->createLandlordWithFullSetup();
        $tenantSetup = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        Invoice::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'lease_id' => $tenantSetup['lease']->id,
            'late_fees_total' => '0.00',
        ]);

        $exitCode = $this->artisan('latefees:audit-drift')->run();

        $this->assertSame(0, $exitCode, 'latefees:audit-drift must pass on the zero-fees-zero-total path.');
    }

    public function test_audit_late_fees_drift_command_registered(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('latefees:audit-drift')
            ->run();
    }

    public function test_audit_late_fees_drift_scheduled_in_routes_console(): void
    {
        // INDEX-1 schedule wiring: the command must be scheduled so it
        // actually runs in production. Phase-17/18 had similar regressions
        // (command existed, schedule entry missing).
        $contents = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString(
            "'latefees:audit-drift'",
            $contents,
            'Phase-19 INDEX-1: latefees:audit-drift must be wired into routes/console.php.',
        );

        $this->assertStringContainsString(
            '05:40',
            $contents,
            'Phase-19 INDEX-1: latefees:audit-drift must run at 05:40 (10min after wallets:audit-balances).',
        );
    }
}
