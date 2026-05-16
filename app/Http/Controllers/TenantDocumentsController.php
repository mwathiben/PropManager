<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\TenantKycSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-28 TENANT-DOCS-1/2: tenant document repository — lease documents,
 * payment receipts, and KYC submissions categorised on one page. Uses
 * existing Document / Receipt / TenantKycSubmission models + policies
 * (DocumentPolicy::view already gates on documentable->tenant_id and the
 * Receipt model is scoped via TenantScope landlord_id).
 */
class TenantDocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user();
        $leaseIds = $tenant->leases()->pluck('id');

        $leaseDocs = Document::query()
            ->where('documentable_type', Lease::class)
            ->whereIn('documentable_id', $leaseIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Document $d) => $this->presentDocument($d, 'lease'))
            ->all();

        $receipts = Receipt::query()
            ->whereIn('lease_id', $leaseIds)
            ->orderByDesc('issued_at')
            ->get()
            ->map(fn (Receipt $r) => [
                'id' => $r->id,
                'type' => 'receipt',
                'title' => $r->receipt_number,
                'description' => $r->payment_method,
                'amount' => (float) $r->amount,
                'date' => $r->issued_at?->toDateString(),
                'download_url' => route('payments.downloadReceipt', ['payment' => $r->payment_id]),
            ])
            ->all();

        $kycDocs = Document::query()
            ->where('documentable_type', TenantKycSubmission::class)
            ->whereIn('documentable_id', TenantKycSubmission::query()
                ->where('user_id', $tenant->id)
                ->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Document $d) => $this->presentDocument($d, 'kyc'))
            ->all();

        return Inertia::render('Tenant/Documents', [
            'leaseDocuments' => $leaseDocs,
            'receipts' => $receipts,
            'kycDocuments' => $kycDocs,
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('view', $document);

        abort_unless($document->fileExists(), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     title: string,
     *     document_type: string,
     *     size: string,
     *     mime: string,
     *     date: string|null,
     *     expires_at: string|null,
     *     download_url: string
     * }
     */
    private function presentDocument(Document $document, string $category): array
    {
        return [
            'id' => $document->id,
            'type' => $category,
            'title' => $document->title,
            'document_type' => $document->document_type,
            'size' => $document->file_size_formatted,
            'mime' => $document->mime_type,
            'date' => $document->created_at?->toDateString(),
            'expires_at' => $document->expires_at?->toDateString(),
            'download_url' => route('tenant.documents.download', ['document' => $document->id]),
        ];
    }
}
