<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function createReceipt(Payment $payment, ?Invoice $invoice = null): Receipt
    {
        $invoice = $invoice ?? $payment->invoice;
        $landlordId = $payment->landlord_id;
        $landlord = User::find($landlordId);

        $receiptNumber = $this->generateReceiptNumber($landlord);

        $isPartial = $invoice
            ? ($invoice->amount_paid < $invoice->total_due)
            : false;

        return Receipt::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice?->id,
            'lease_id' => $payment->lease_id,
            'landlord_id' => $landlordId,
            'receipt_number' => $receiptNumber,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'is_partial' => $isPartial,
            'issued_at' => now(),
        ]);
    }

    protected function generateReceiptNumber(?User $landlord): string
    {
        $settings = $landlord?->invoiceSetting;

        if ($settings) {
            return $settings->getNextReceiptNumber();
        }

        $prefix = 'RCT';
        $year = date('Y');
        $month = date('m');
        $pattern = "{$prefix}-{$year}{$month}-%";

        $count = Receipt::where('receipt_number', 'like', $pattern)
            ->lockForUpdate()
            ->count();

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $count + 1);
    }

    protected function getReceiptSettings(Receipt $receipt): object
    {
        $landlord = User::find($receipt->landlord_id);
        $settings = $landlord?->invoiceSetting;

        return (object) [
            'receipt_show_logo' => $settings?->receipt_show_logo ?? true,
            'receipt_show_tenant_details' => $settings?->receipt_show_tenant_details ?? true,
            'receipt_show_invoice_details' => $settings?->receipt_show_invoice_details ?? true,
            'receipt_show_payment_method' => $settings?->receipt_show_payment_method ?? true,
            'receipt_header_text' => $settings?->receipt_header_text,
            'receipt_footer_text' => $settings?->receipt_footer_text,
            'receipt_thank_you_message' => $settings?->receipt_thank_you_message,
            'logo_path' => $settings?->logo_path,
        ];
    }

    public function generatePdf(Receipt $receipt): string
    {
        $receipt->load([
            'payment',
            'invoice.lease.tenant',
            'invoice.lease.unit.building',
        ]);

        $pdf = Pdf::loadView('receipts.payment-receipt', [
            'payment' => $receipt->payment,
            'invoice' => $receipt->invoice,
            'receipt' => $receipt,
            'settings' => $this->getReceiptSettings($receipt),
        ]);

        $filename = "receipts/{$receipt->landlord_id}/{$receipt->receipt_number}.pdf";
        Storage::disk('private')->put($filename, $pdf->output());

        $receipt->update(['pdf_path' => $filename]);

        return $filename;
    }

    public function streamPdf(Receipt $receipt)
    {
        $receipt->load([
            'payment',
            'invoice.lease.tenant',
            'invoice.lease.unit.building',
        ]);

        return Pdf::loadView('receipts.payment-receipt', [
            'payment' => $receipt->payment,
            'invoice' => $receipt->invoice,
            'receipt' => $receipt,
            'settings' => $this->getReceiptSettings($receipt),
        ])->stream("Receipt-{$receipt->receipt_number}.pdf");
    }

    public function downloadPdf(Receipt $receipt)
    {
        $receipt->load([
            'payment',
            'invoice.lease.tenant',
            'invoice.lease.unit.building',
        ]);

        return Pdf::loadView('receipts.payment-receipt', [
            'payment' => $receipt->payment,
            'invoice' => $receipt->invoice,
            'receipt' => $receipt,
            'settings' => $this->getReceiptSettings($receipt),
        ])->download("Receipt-{$receipt->receipt_number}.pdf");
    }
}
