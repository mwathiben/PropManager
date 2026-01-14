<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function __construct(
        protected PaymentQrCodeService $qrCodeService
    ) {}

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

    protected function getReceiptTemplate(Receipt $receipt): ReceiptTemplate
    {
        $template = ReceiptTemplate::getDefaultForLandlord($receipt->landlord_id);

        if ($template) {
            return $template;
        }

        return new ReceiptTemplate([
            'design' => ReceiptTemplate::DESIGN_CLASSIC,
            'show_logo' => true,
            'show_receipt_number' => true,
            'show_payment_date' => true,
            'show_payment_method' => true,
            'show_transaction_reference' => true,
            'show_amount_breakdown' => false,
            'show_tenant_name' => true,
            'show_tenant_email' => true,
            'show_tenant_phone' => false,
            'show_unit_details' => true,
            'show_building_name' => true,
            'show_invoice_details' => true,
            'show_invoice_breakdown' => false,
            'show_balance_after_payment' => true,
            'show_thank_you_message' => true,
            'show_qr_code' => false,
            'show_footer' => true,
            'thank_you_message' => 'Thank you for your payment!',
            'primary_color' => '#059669',
            'secondary_color' => '#10B981',
        ]);
    }

    protected function getBusinessSettings(Receipt $receipt): object
    {
        $landlord = User::find($receipt->landlord_id);
        $settings = $landlord?->invoiceSetting;

        return (object) [
            'business_name' => $settings?->business_name ?? $landlord?->name ?? 'Property Management',
            'business_address' => $settings?->business_address ?? '',
            'business_phone' => $settings?->business_phone ?? '',
            'logo_path' => $settings?->logo_path,
        ];
    }

    protected function prepareReceiptData(Receipt $receipt, ReceiptTemplate $template): array
    {
        $receipt->load([
            'payment',
            'invoice.lease.tenant',
            'invoice.lease.unit.building',
            'invoice.items',
        ]);

        $payment = $receipt->payment;
        $invoice = $receipt->invoice;
        $tenant = $invoice?->lease?->tenant;
        $unit = $invoice?->lease?->unit;
        $building = $unit?->building;
        $business = $this->getBusinessSettings($receipt);

        $qrCode = null;
        if ($template->show_qr_code) {
            $qrCode = $this->qrCodeService->generateReceiptQrCode($payment, [
                'size' => 120,
                'primary_color' => $template->primary_color ?? '#000000',
            ]);
        }

        return [
            'receipt' => $receipt,
            'payment' => $payment,
            'invoice' => $invoice,
            'tenant' => $tenant,
            'unit' => $unit,
            'building' => $building,
            'template' => $template,
            'business' => $business,
            'qr_code' => $qrCode,
        ];
    }

    public function generatePdf(Receipt $receipt, ?ReceiptTemplate $template = null): string
    {
        $template = $template ?? $this->getReceiptTemplate($receipt);
        $data = $this->prepareReceiptData($receipt, $template);

        $pdf = Pdf::loadView('receipts.templated-receipt', $data);

        $filename = "receipts/{$receipt->landlord_id}/{$receipt->receipt_number}.pdf";
        Storage::disk('private')->put($filename, $pdf->output());

        $receipt->update(['pdf_path' => $filename]);

        return $filename;
    }

    public function streamPdf(Receipt $receipt, ?ReceiptTemplate $template = null)
    {
        $template = $template ?? $this->getReceiptTemplate($receipt);
        $data = $this->prepareReceiptData($receipt, $template);

        return Pdf::loadView('receipts.templated-receipt', $data)
            ->stream("Receipt-{$receipt->receipt_number}.pdf");
    }

    public function downloadPdf(Receipt $receipt, ?ReceiptTemplate $template = null)
    {
        $template = $template ?? $this->getReceiptTemplate($receipt);
        $data = $this->prepareReceiptData($receipt, $template);

        return Pdf::loadView('receipts.templated-receipt', $data)
            ->download("Receipt-{$receipt->receipt_number}.pdf");
    }
}
