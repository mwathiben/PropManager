<?php

namespace App\Console\Commands;

use App\Services\LateFeeService;
use App\Services\MetricsService;
use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature = 'invoices:apply-late-fees
                            {--dry-run : Preview without applying fees}';

    protected $description = 'Apply late fees to overdue invoices based on configured policies';

    public function handle(LateFeeService $lateFeeService, MetricsService $metrics): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No fees will be applied');
        }

        $this->info('Processing overdue invoices for late fees...');

        $results = $lateFeeService->processAllOverdueInvoices();

        $this->info("Processed: {$results['processed']}");
        $this->info("Fees Applied: {$results['fees_applied']}");
        $this->info("Skipped: {$results['skipped']}");

        // OBS-11: late-fee application is a tenant-billing event; the
        // rate of fees-applied vs processed answers "did the run do
        // any work last night?" without grepping logs.
        $metrics->increment('late_fees.processed', $results['processed'] ?? 0);
        $metrics->increment('late_fees.applied', $results['fees_applied'] ?? 0);
        $metrics->increment('late_fees.skipped', $results['skipped'] ?? 0);
        if (! empty($results['errors'])) {
            $metrics->increment('late_fees.errors', count($results['errors']));
        }

        if (! empty($results['errors'])) {
            $this->error('Errors: '.count($results['errors']));
            foreach ($results['errors'] as $error) {
                $this->line("  - Invoice #{$error['invoice_id']}: {$error['error']}");
            }
        }

        return self::SUCCESS;
    }
}
