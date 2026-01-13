<?php

namespace App\Console\Commands;

use App\Services\LateFeeService;
use Illuminate\Console\Command;

class ApplyLateFees extends Command
{
    protected $signature = 'invoices:apply-late-fees
                            {--dry-run : Preview without applying fees}';

    protected $description = 'Apply late fees to overdue invoices based on configured policies';

    public function handle(LateFeeService $lateFeeService): int
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

        if (! empty($results['errors'])) {
            $this->error('Errors: '.count($results['errors']));
            foreach ($results['errors'] as $error) {
                $this->line("  - Invoice #{$error['invoice_id']}: {$error['error']}");
            }
        }

        return self::SUCCESS;
    }
}
