<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\LandlordWalletSetting;
use App\Models\Lease;
use App\Services\Wallet\WalletService;
use App\Services\WorkflowLogger;
use Illuminate\Console\Command;

/**
 * Phase-76 WALLET-DEEP AUTO-APPLY-2: for landlords in oldest_first_sweep mode,
 * apply each lease's standing wallet credit to its oldest unpaid invoices, in
 * matching currency only. Idempotent — a re-run applies whatever credit remains
 * and stops once credit or invoices are exhausted.
 *
 * Scheduled daily 05:15 Africa/Nairobi (before invoices age overnight).
 */
class ApplyWalletCredit extends Command
{
    protected $signature = 'wallet:auto-apply {--dry-run}';

    protected $description = 'Phase-76 AUTO-APPLY-2: sweep standing tenant wallet credit onto oldest unpaid invoices.';

    public function handle(WalletService $wallet, WorkflowLogger $workflowLogger): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $landlordIds = LandlordWalletSetting::query()
            ->where('auto_apply_mode', LandlordWalletSetting::MODE_OLDEST_FIRST_SWEEP)
            ->pluck('landlord_id');

        $applied = 0;
        $leasesTouched = 0;

        foreach ($landlordIds as $landlordId) {
            $leases = Lease::query()
                ->withoutGlobalScope('landlord')
                ->where('landlord_id', $landlordId)
                ->get();

            foreach ($leases as $lease) {
                [$leaseApplied, $leaseApplications] = $this->processLease($landlordId, $lease, $wallet, $dryRun);

                $applied += $leaseApplications;

                if ($leaseApplied || $dryRun) {
                    $leasesTouched++;
                }
            }
        }

        $this->info("wallet:auto-apply: {$applied} application(s) across {$leasesTouched} lease(s)".($dryRun ? ' (dry-run)' : ''));

        $workflowLogger->log(
            workflowName: 'wallet:auto-apply',
            action: 'completed',
            metadata: ['applications' => $applied, 'leases' => $leasesTouched, 'dry_run' => $dryRun],
        );

        return self::SUCCESS;
    }

    /**
     * Process a single lease: apply wallet credit across all currency balances.
     *
     * @return array{bool, int} [leaseApplied, applicationsCount]
     */
    private function processLease(int $landlordId, Lease $lease, WalletService $wallet, bool $dryRun): array
    {
        $balances = $wallet->balancesFor($lease);
        if ($balances === []) {
            return [false, 0];
        }

        $leaseApplied = false;
        $applied = 0;

        foreach ($balances as $code => $balance) {
            $currency = Currency::from($code);
            [$currencyApplied, $currencyApplications] = $this->applyCurrencyCredit($lease, $currency, $wallet, $dryRun);

            if ($currencyApplied) {
                $leaseApplied = true;
            }
            $applied += $currencyApplications;
        }

        return [$leaseApplied, $applied];
    }

    /**
     * Apply wallet credit for one currency against oldest unpaid invoices.
     *
     * @return array{bool, int} [anyApplied, applicationsCount]
     */
    private function applyCurrencyCredit(Lease $lease, Currency $currency, WalletService $wallet, bool $dryRun): array
    {
        $invoices = $this->oldestUnpaid($lease->landlord_id, $lease->id, $currency);
        $applied = false;
        $count = 0;

        foreach ($invoices as $invoice) {
            if ($wallet->balanceFor($lease->fresh(), $currency) <= 0) {
                break;
            }
            if ($dryRun) {
                continue;
            }
            $drawn = $wallet->applyToInvoice($invoice);
            if ($drawn <= 0) {
                // Credit exhausted (the locked-row cap returned 0) —
                // stop walking this currency's invoices.
                break;
            }
            $count++;
            $applied = true;
        }

        return [$applied, $count];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    private function oldestUnpaid(int $landlordId, int $leaseId, Currency $currency)
    {
        return Invoice::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('lease_id', $leaseId)
            ->where('currency', $currency->value)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::Partial, InvoiceStatus::Overdue])
            ->whereColumn('amount_paid', '<', 'total_due')
            ->orderBy('due_date')
            ->get();
    }
}
