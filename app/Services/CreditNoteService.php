<?php

namespace App\Services;

use App\Mail\CreditNoteIssued;
use App\Models\CreditNote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CreditNoteService
{
    public function __construct(
        protected PaymentQrCodeService $qrCodeService
    ) {}

    protected function getBusinessSettings(CreditNote $creditNote): object
    {
        $landlord = User::find($creditNote->landlord_id);
        $settings = $landlord?->invoiceSetting;

        return (object) [
            'business_name' => $settings?->business_name ?? $landlord?->name ?? 'Property Management',
            'business_address' => $settings?->business_address ?? '',
            'business_phone' => $settings?->business_phone ?? '',
            'business_email' => $settings?->business_email ?? $landlord?->email ?? '',
            'logo_path' => $settings?->logo_path,
        ];
    }

    protected function prepareCreditNoteData(CreditNote $creditNote): array
    {
        $creditNote->load([
            'tenant',
            'lease.unit.building',
            'invoice',
            'appliedToInvoice',
            'approvedByUser',
        ]);

        $business = $this->getBusinessSettings($creditNote);

        $qrData = implode("\n", [
            'CREDIT NOTE',
            'Number: '.$creditNote->credit_number,
            'Amount: KES '.number_format($creditNote->amount, 2),
            'Date: '.$creditNote->created_at->format('Y-m-d'),
            'Tenant: '.$creditNote->tenant?->name,
            'Status: '.ucfirst($creditNote->status),
        ]);

        $qrCode = $this->qrCodeService->generateBase64QrCode($qrData, ['size' => 120]);

        return [
            'creditNote' => $creditNote,
            'tenant' => $creditNote->tenant,
            'unit' => $creditNote->lease?->unit,
            'building' => $creditNote->lease?->unit?->building,
            'invoice' => $creditNote->invoice,
            'appliedToInvoice' => $creditNote->appliedToInvoice,
            'approver' => $creditNote->approvedByUser,
            'business' => $business,
            'qr_code' => $qrCode,
        ];
    }

    public function generatePdf(CreditNote $creditNote): string
    {
        $data = $this->prepareCreditNoteData($creditNote);

        $pdf = Pdf::loadView('credit-notes.pdf', $data);

        $filename = "credit-notes/{$creditNote->landlord_id}/{$creditNote->credit_number}.pdf";
        Storage::disk('private')->put($filename, $pdf->output());

        return $filename;
    }

    public function streamPdf(CreditNote $creditNote)
    {
        $data = $this->prepareCreditNoteData($creditNote);

        return Pdf::loadView('credit-notes.pdf', $data)
            ->stream("CreditNote-{$creditNote->credit_number}.pdf");
    }

    public function downloadPdf(CreditNote $creditNote)
    {
        $data = $this->prepareCreditNoteData($creditNote);

        return Pdf::loadView('credit-notes.pdf', $data)
            ->download("CreditNote-{$creditNote->credit_number}.pdf");
    }

    public function sendApprovalNotification(CreditNote $creditNote): void
    {
        if (! $creditNote->tenant?->email) {
            return;
        }

        $creditNote->load(['tenant', 'lease.unit.building', 'invoice', 'approvedByUser']);

        $pdfPath = $this->generatePdf($creditNote);

        Mail::to($creditNote->tenant->email)
            ->queue(new CreditNoteIssued($creditNote, $pdfPath));
    }
}
