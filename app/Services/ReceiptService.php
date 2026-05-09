<?php

namespace App\Services;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
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

    // CONC-1: prefer the InvoiceSetting path, which serializes via lockForUpdate.
    // The fallback count()+1 is only reachable when a landlord has no
    // invoice_settings row; the backfill migration covers existing landlords,
    // and InvoiceSetting::createDefaultsForLandlord backstops new ones. The
    // unique index on receipt_number is the canonical guarantee.
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

        $currencySymbol = ($payment->currency ?? Currency::default())->symbol();

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
            'currency_symbol' => $currencySymbol,
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

    public function streamPreviewPdf(InvoiceSetting $settings, ?ReceiptTemplate $template = null)
    {
        $template = $template ?? $this->buildDefaultTemplate();
        $data = $this->buildSamplePreviewData($settings, $template);

        return Pdf::loadView('receipts.templated-receipt', $data)
            ->stream('receipt-preview.pdf');
    }

    protected function buildSamplePreviewData(InvoiceSetting $settings, ReceiptTemplate $template): array
    {
        $business = (object) [
            'business_name' => $settings->business_name ?? 'Property Management',
            'business_address' => $settings->business_address ?? '',
            'business_phone' => $settings->business_phone ?? '',
            'logo_path' => $settings->logo_path,
        ];

        $sampleReceipt = new Receipt([
            'receipt_number' => 'RCT-PREVIEW-0001',
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'issued_at' => now(),
        ]);

        $samplePayment = (object) [
            'reference' => 'PAY-PREVIEW-0001',
            'payment_date' => now(),
            'payment_method' => 'mpesa',
            'amount' => 25000,
            'notes' => 'Sample payment for preview',
        ];

        $sampleInvoice = (object) [
            'invoice_number' => 'INV-PREVIEW-0001',
            'billing_period_start' => now()->startOfMonth(),
            'total_due' => 25000,
            'amount_paid' => 25000,
            'items' => collect(),
            'lease' => (object) [
                'tenant' => (object) ['name' => 'John Doe', 'email' => 'johndoe@example.com', 'phone' => '0712345678'],
                'unit' => (object) ['unit_number' => 'A101', 'building' => (object) ['name' => 'Sunrise Apartments']],
            ],
        ];

        return [
            'receipt' => $sampleReceipt,
            'payment' => $samplePayment,
            'invoice' => $sampleInvoice,
            'tenant' => $sampleInvoice->lease->tenant,
            'unit' => $sampleInvoice->lease->unit,
            'building' => $sampleInvoice->lease->unit->building,
            'template' => $template,
            'business' => $business,
            'qr_code' => $template->show_qr_code ? $this->generateSampleQrCode() : null,
            'currency_symbol' => Currency::default()->symbol(),
        ];
    }

    protected function generateSampleQrCode(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">'
            .'<rect width="100" height="100" fill="#f3f4f6"/>'
            .'<text x="50" y="50" text-anchor="middle" dominant-baseline="middle" font-size="10" fill="#9ca3af">QR Preview</text>'
            .'</svg>'
        );
    }

    protected function buildDefaultTemplate(): ReceiptTemplate
    {
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
}
