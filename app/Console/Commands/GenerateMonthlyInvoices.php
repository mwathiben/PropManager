<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    /**
     * Phase-19 POLICY-9: guard rails on a cross-tenant cron.
     *   --month / --year — billing period (existing).
     *   --landlord-id    — scope to a single landlord (operator override).
     *   --dry-run        — log+report what would be generated, mutate nothing.
     *   --confirm        — required for interactive runs (skipped in
     *                      scheduler context via --no-interaction).
     */
    protected $signature = 'invoices:generate {--month=} {--year=} {--landlord-id=} {--dry-run} {--confirm}';

    protected $description = 'Generate monthly invoices for all active leases (or a single landlord with --landlord-id)';

    public function handle(InvoiceService $invoiceService)
    {
        $month = $this->option('month') ?: now()->month;
        $year = $this->option('year') ?: now()->year;
        $billingPeriod = Carbon::create($year, $month, 1);
        $landlordId = $this->option('landlord-id') !== null ? (int) $this->option('landlord-id') : null;
        $dryRun = (bool) $this->option('dry-run');

        // POLICY-9: interactive invocations without --confirm are
        // refused — protects against accidental cross-tenant mutation
        // from an operator shell. Scheduler runs use --no-interaction
        // so this branch is skipped there.
        if (! $dryRun && ! $this->option('confirm') && $this->input->isInteractive()) {
            $this->error('Refusing to run without --confirm in interactive mode (POLICY-9). Add --dry-run to preview.');

            return Command::FAILURE;
        }

        Log::info('GenerateMonthlyInvoices: Starting invoice generation', [
            'billing_period' => $billingPeriod->format('Y-m'),
            'landlord_id' => $landlordId,
            'dry_run' => $dryRun,
            'triggered_by' => 'artisan_command',
        ]);

        $scopeLabel = $landlordId !== null ? "landlord_id={$landlordId}" : 'ALL landlords';
        $modeLabel = $dryRun ? ' (DRY RUN)' : '';
        $this->info("Generating invoices for {$billingPeriod->format('F Y')} — {$scopeLabel}{$modeLabel}");

        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0, 'would_generate' => 0];

        // Cross-tenant by design: the scheduled run iterates every
        // landlord. The --landlord-id flag is the operator override
        // for single-landlord runs (POLICY-9).
        Lease::withoutGlobalScope('landlord')
            ->where('is_active', true)
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['unit', 'tenant'])
            ->chunkById(500, function ($leases) use ($invoiceService, $billingPeriod, &$stats, $dryRun) {
                foreach ($leases as $lease) {
                    if ($dryRun) {
                        $this->line('[DRY-RUN] would generate for unit '.$lease->unit->unit_number.' (lease_id='.$lease->id.')');
                        $stats['would_generate']++;

                        continue;
                    }
                    try {
                        $invoice = $invoiceService->generateInvoiceForLease($lease, $billingPeriod);

                        if ($invoice->wasRecentlyCreated) {
                            $this->line('Generated '.$invoice->invoice_number.' for '.$lease->unit->unit_number);
                            $stats['generated']++;
                        } else {
                            $this->line('Skipped (exists): '.$invoice->invoice_number.' for '.$lease->unit->unit_number);
                            $stats['skipped']++;
                        }
                    } catch (\Exception $e) {
                        $this->error('Failed for unit '.$lease->unit->unit_number.': '.$e->getMessage());
                        Log::error('GenerateMonthlyInvoices: Failed to generate invoice', [
                            'lease_id' => $lease->id,
                            'unit_number' => $lease->unit->unit_number,
                            'error' => $e->getMessage(),
                        ]);
                        $stats['failed']++;
                    }
                }
            });

        Log::info('GenerateMonthlyInvoices: Completed', $stats);

        if ($dryRun) {
            $this->info("[DRY-RUN] would generate {$stats['would_generate']} invoices — no DB writes.");
        } else {
            $this->info("Generated {$stats['generated']}, skipped {$stats['skipped']}, failed {$stats['failed']}");
        }

        return Command::SUCCESS;
    }
}
