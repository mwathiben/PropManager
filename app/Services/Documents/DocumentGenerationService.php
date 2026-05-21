<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Lease;
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
        $landlordId = (int) $lease->landlord_id;

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

        $fileName = $noticeType.'_notice_'.now()->format('Ymd_His').'.pdf';
        $path = "documents/{$landlordId}/Lease/{$fileName}";
        Storage::tenant($landlordId)->put($path, $pdf->output());

        return Document::create([
            'landlord_id' => $landlordId,
            'documentable_id' => $lease->id,
            'documentable_type' => Lease::class,
            'title' => __('document.notice.heading_'.$noticeType),
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => strlen((string) $pdf->output()),
            'document_type' => 'notice',
            'issue_date' => now()->toDateString(),
            'is_renewable' => false,
            'uploaded_by' => $actor->id,
        ]);
    }
}
