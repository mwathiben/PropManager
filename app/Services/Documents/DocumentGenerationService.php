<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Phase-82 NOTICE-GEN-1: render a notice to PDF (the proven InvoicePdfService +
 * dompdf + blade pattern) and store it as a Document attached to the lease, so
 * generated notices live in the same archive/retention/legal-hold pipeline as
 * uploaded documents (rather than being emailed text only).
 */
class DocumentGenerationService
{
    public const NOTICE_TYPES = ['rent_increase', 'arrears', 'general'];

    /**
     * @param  array{reason?:string, effective_date?:string}  $data
     */
    public function generateNotice(Lease $lease, string $noticeType, array $data, User $actor): Document
    {
        if (! in_array($noticeType, self::NOTICE_TYPES, true)) {
            $noticeType = 'general';
        }

        $lease->loadMissing(['tenant', 'unit.building.property']);

        $pdf = Pdf::loadView('documents.notice', [
            'noticeType' => $noticeType,
            'lease' => $lease,
            'tenant' => $lease->tenant,
            'unit' => $lease->unit,
            'building' => $lease->unit?->building,
            'property' => $lease->unit?->building?->property,
            'reason' => $data['reason'] ?? null,
            'effectiveDate' => $data['effective_date'] ?? null,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->persist(
            $lease,
            $pdf->output(),
            $noticeType.'_notice_'.now()->format('Ymd_His').'.pdf',
            __('document.notice.heading_'.$noticeType),
            'notice',
            $actor,
        );
    }

    /**
     * Phase-83 LEASE-DOC-GEN-1: render the lease agreement (parties, term, rent,
     * deposit, co-tenants, guarantors) to PDF and store it as a lease_agreement
     * Document — so the agreement lives in the same archive/retention/hold
     * pipeline as uploaded ones (rather than upload-only).
     */
    public function generateLeaseAgreement(Lease $lease, User $actor): Document
    {
        $lease->loadMissing(['tenant', 'unit.building.property', 'coTenants', 'guarantors']);

        $pdf = Pdf::loadView('documents.lease_agreement', [
            'lease' => $lease,
            'tenant' => $lease->tenant,
            'unit' => $lease->unit,
            'building' => $lease->unit?->building,
            'property' => $lease->unit?->building?->property,
            'coTenants' => $lease->coTenants,
            'guarantors' => $lease->guarantors->where('status', 'active')->values(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->persist(
            $lease,
            $pdf->output(),
            'lease_agreement_'.$lease->id.'_'.now()->format('Ymd_His').'.pdf',
            __('lease_doc.agreement.title'),
            'lease_agreement',
            $actor,
        );
    }

    /**
     * Phase-83 LEASE-DOC-GEN-2: render a renewal offer (current vs proposed
     * rent/end date) to PDF, stored as a Document on the lease.
     */
    public function generateRenewalOffer(LeaseRenewal $renewal, User $actor): Document
    {
        $lease = $renewal->lease;
        $lease->loadMissing(['tenant', 'unit.building.property']);

        $pdf = Pdf::loadView('documents.renewal_offer', [
            'renewal' => $renewal,
            'lease' => $lease,
            'tenant' => $lease->tenant,
            'unit' => $lease->unit,
            'property' => $lease->unit?->building?->property,
            'currentRent' => (float) $lease->rent_amount,
            'proposedRent' => (float) $renewal->proposed_rent_amount_cents / 100,
            'proposedEndDate' => $renewal->proposed_end_date,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        return $this->persist(
            $lease,
            $pdf->output(),
            'renewal_offer_'.$renewal->id.'_'.now()->format('Ymd_His').'.pdf',
            __('lease_doc.renewal.title'),
            'notice',
            $actor,
        );
    }

    /**
     * Store generated PDF bytes on the tenant disk + create the Document row.
     */
    private function persist(Lease $lease, string $bytes, string $fileName, string $title, string $documentType, User $actor): Document
    {
        $landlordId = (int) $lease->landlord_id;
        $path = "documents/{$landlordId}/Lease/{$fileName}";
        Storage::tenant($landlordId)->put($path, $bytes);

        return Document::create([
            'landlord_id' => $landlordId,
            'documentable_id' => $lease->id,
            'documentable_type' => Lease::class,
            'title' => $title,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($bytes),
            'document_type' => $documentType,
            'issue_date' => now()->toDateString(),
            'is_renewable' => false,
            'uploaded_by' => $actor->id,
        ]);
    }
}
