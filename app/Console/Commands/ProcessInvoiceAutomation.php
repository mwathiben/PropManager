<?php

namespace App\Console\Commands;

use App\Services\InvoiceAutomationService;
use Illuminate\Console\Command;

class ProcessInvoiceAutomation extends Command
{
    protected $signature = 'invoices:automate
                            {--day= : Override the day of month to process (1-28)}
                            {--dry-run : Preview what would be generated without creating invoices}';

    protected $description = 'Process automated invoice generation for buildings configured for today';

    public function handle(InvoiceAutomationService $automationService): int
    {
        $day = $this->option('day') ?? now()->day;
        $dryRun = $this->option('dry-run');

        $this->info("Processing invoice automation for day {$day}...");

        $buildings = $automationService->getBuildingsForDay($day);

        if ($buildings->isEmpty()) {
            $this->info('No buildings configured for automation on day '.$day);

            return self::SUCCESS;
        }

        $this->info("Found {$buildings->count()} building(s) configured for day {$day}");

        if ($dryRun) {
            return $this->runDryRun($automationService, $buildings);
        }

        return $this->runAutomation($automationService, $day);
    }

    private function runDryRun(InvoiceAutomationService $automationService, \Illuminate\Support\Collection $buildings): int
    {
        $this->info('DRY RUN - No invoices will be generated');
        $this->newLine();

        foreach ($buildings as $building) {
            $preview = $automationService->previewAutomation($building);

            $this->info("Building: {$building->name}");
            $this->info('  Auto-send emails: '.($building->auto_send_invoices ? 'Yes' : 'No'));
            $this->info('  Units to invoice: '.count($preview['units_to_invoice']));
            $this->info('  Units already invoiced: '.count($preview['units_already_invoiced']));

            if (! empty($preview['units_to_invoice'])) {
                $this->table(
                    ['Unit', 'Tenant', 'Rent'],
                    collect($preview['units_to_invoice'])->map(fn ($u) => [
                        $u['unit_number'],
                        $u['tenant_name'],
                        number_format($u['rent_amount'], 2),
                    ])
                );
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function runAutomation(InvoiceAutomationService $automationService, int|string $day): int
    {
        $results = $automationService->processAutomatedInvoices($day);

        $this->info('Automation complete:');
        $this->info("  Buildings processed: {$results['buildings_processed']}");
        $this->info("  Invoices generated: {$results['invoices_generated']}");
        $this->info("  Invoices sent: {$results['invoices_sent']}");
        $this->info("  Late fees applied: {$results['late_fees_applied']}");

        if (! empty($results['errors'])) {
            $this->warn('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->error("  Building {$error['building_name']}: {$error['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
