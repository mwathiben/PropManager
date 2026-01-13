<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate {--month=} {--year=}';

    protected $description = 'Generate monthly invoices for all active leases';

    public function handle(InvoiceService $invoiceService)
    {
        $month = $this->option('month') ?: now()->month;
        $year = $this->option('year') ?: now()->year;
        $billingPeriod = Carbon::create($year, $month, 1);

        $this->info('Generating invoices for '.$billingPeriod->format('F Y'));

        $leases = Lease::where('is_active', true)->with(['unit', 'tenant'])->get();

        $successCount = 0;
        foreach ($leases as $lease) {
            try {
                $invoice = $invoiceService->generateInvoiceForLease($lease, $billingPeriod);
                $this->line('Generated '.$invoice->invoice_number.' for '.$lease->unit->unit_number);
                $successCount++;
            } catch (\Exception $e) {
                $this->error('Failed for unit '.$lease->unit->unit_number.': '.$e->getMessage());
            }
        }

        $this->info("Generated $successCount invoices successfully.");

        return Command::SUCCESS;
    }
}
