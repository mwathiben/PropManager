<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoicePdf implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public int $invoiceId
    ) {}

    public function handle(InvoicePdfService $pdfService): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            Log::warning('GenerateInvoicePdf: Invoice not found', ['invoice_id' => $this->invoiceId]);

            return;
        }

        try {
            $pdfService->savePdfAndRecord($invoice);
        } catch (\Exception $e) {
            Log::error('GenerateInvoicePdf failed', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateInvoicePdf permanently failed', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
