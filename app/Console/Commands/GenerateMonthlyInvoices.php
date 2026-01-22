<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate {--month=} {--year=}';

    protected $description = 'Generate monthly invoices for all active leases';

    public function handle(InvoiceService $invoiceService)
    {
        $month = $this->option('month') ?: now()->month;
        $year = $this->option('year') ?: now()->year;
        $billingPeriod = Carbon::create($year, $month, 1);

        Log::info('GenerateMonthlyInvoices: Starting invoice generation', [
            'billing_period' => $billingPeriod->format('Y-m'),
            'triggered_by' => 'artisan_command',
        ]);

        $this->info('Generating invoices for '.$billingPeriod->format('F Y'));

        $stats = ['generated' => 0, 'skipped' => 0, 'failed' => 0];

        Lease::where('is_active', true)
            ->with(['unit', 'tenant'])
            ->chunkById(500, function ($leases) use ($invoiceService, $billingPeriod, &$stats) {
                foreach ($leases as $lease) {
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
        $this->info("Generated {$stats['generated']}, skipped {$stats['skipped']}, failed {$stats['failed']}");

        return Command::SUCCESS;
    }
}
