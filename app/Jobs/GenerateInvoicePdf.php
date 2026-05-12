<?php

namespace App\Jobs;

use App\Jobs\Concerns\TracksFailures;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdf implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, TracksFailures;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Phase-16 QUEUE-1: explicit timeout. Pre-fix this defaulted to the
     * 60s worker timeout, which a 30+ page invoice render via dompdf
     * routinely exceeds — SIGTERM mid-render leaves the worker dead and
     * the job in failed_jobs as 'process killed'. 5 minutes covers the
     * worst-case render observed in production.
     */
    public int $timeout = 300;

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

        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            Log::info('GenerateInvoicePdf: PDF already exists, skipping regeneration', [
                'invoice_id' => $this->invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'pdf_path' => $invoice->pdf_path,
                'pdf_generated_at' => $invoice->pdf_generated_at?->toDateTimeString(),
            ]);

            return;
        }

        try {
            Log::info('GenerateInvoicePdf: Generating PDF', [
                'invoice_id' => $this->invoiceId,
                'invoice_number' => $invoice->invoice_number,
            ]);

            $pdfService->savePdfAndRecord($invoice);

            Log::info('GenerateInvoicePdf: PDF generated successfully', [
                'invoice_id' => $this->invoiceId,
                'invoice_number' => $invoice->invoice_number,
                'pdf_path' => $invoice->fresh()->pdf_path,
            ]);
        } catch (\Exception $e) {
            Log::error('GenerateInvoicePdf: Failed', [
                'invoice_id' => $this->invoiceId,
                'invoice_number' => $invoice->invoice_number,
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

        $this->recordJobFailure($exception);
    }
}
