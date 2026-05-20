<?php

namespace App\Http\Controllers;

use App\Exceptions\LegalHoldActiveException;
use App\Models\Document;
use App\Models\Lease;
use App\Models\LegalHold;
use App\Models\User;
use App\Rules\SecureFile;
use App\Support\LegalHoldRegistry;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocumentController extends Controller
{
    use HasBuildingFilter;

    /**
     * Resolve the landlord ID for the current user.
     * Caretakers operate on behalf of their landlord, not their own user record.
     */
    private function resolveLandlordId(): int
    {
        $user = auth()->user();

        return $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;
    }

    /**
     * Display a listing of documents for the authenticated landlord
     */
    public function index(Request $request)
    {
        $landlordId = $this->resolveLandlordId();
        $query = Document::where('landlord_id', $landlordId)
            ->with(['documentable', 'uploader']);

        // Filter by document type
        if ($request->filled('type')) {
            $query->where('document_type', $request->type);
        }

        // VALID-10: only allow whitelisted documentable types — the value is
        // appended to App\Models\ and used in a WHERE clause, so unvalidated
        // input previously enabled querying any class.
        if ($request->filled('model_type')) {
            $allowed = ['Lease', 'User', 'Invoice', 'Payment', 'Unit', 'Building', 'Property'];
            if (in_array($request->model_type, $allowed, true)) {
                $query->where('documentable_type', 'App\\Models\\'.$request->model_type);
            }
        }

        // Building/Wing filter for documents
        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        if ($buildingId || $wingId) {
            $buildingIds = $this->getBuildingIds($buildingId, $wingId);

            $query->where(function ($q) use ($buildingIds) {
                // Lease documents - filter by unit's building
                $q->where(function ($lq) use ($buildingIds) {
                    $lq->where('documentable_type', 'App\\Models\\Lease')
                        ->whereHas('documentable', function ($leaseQ) use ($buildingIds) {
                            $leaseQ->whereHas('unit', function ($unitQ) use ($buildingIds) {
                                $unitQ->whereIn('building_id', $buildingIds);
                            });
                        });
                });

                // User (Tenant) documents - filter by tenant's active lease building
                $q->orWhere(function ($tq) use ($buildingIds) {
                    $tq->where('documentable_type', 'App\\Models\\User')
                        ->whereHas('documentable', function ($userQ) use ($buildingIds) {
                            $userQ->whereHas('leases', function ($leaseQ) use ($buildingIds) {
                                $leaseQ->where('is_active', true)
                                    ->whereHas('unit', function ($unitQ) use ($buildingIds) {
                                        $unitQ->whereIn('building_id', $buildingIds);
                                    });
                            });
                        });
                });
            });
        }

        // Search by title or filename
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('file_name', 'like', '%'.$request->search.'%');
            });
        }

        $documents = $query->latest()->paginate(20);

        // Phase-68 DOC-HOLD-1: surface per-row hold state to landlord/super-admin
        // only. Held set comes from the 60s-cached registry; the active hold id
        // for the rows ON THIS PAGE is resolved in ONE query (no N+1).
        $user = $request->user();
        $canSeeHolds = $user->isLandlord() || $user->isSuperAdmin();
        $holdIdByDocument = [];

        if ($canSeeHolds) {
            $heldIds = LegalHoldRegistry::heldIdsFor(Document::class);
            $heldOnPage = array_values(array_intersect(
                collect($documents->items())->pluck('id')->all(),
                $heldIds,
            ));

            if ($heldOnPage !== []) {
                $holdIdByDocument = LegalHold::query()
                    ->where('holdable_type', Document::class)
                    ->whereIn('holdable_id', $heldOnPage)
                    ->whereNull('released_at')
                    ->pluck('id', 'holdable_id')
                    ->all();
            }
        }

        $documents->through(function ($document) use ($holdIdByDocument, $canSeeHolds) {
            return [
                'id' => $document->id,
                'title' => $document->title,
                'file_name' => $document->file_name,
                'file_size_formatted' => $document->file_size_formatted,
                'file_extension' => $document->file_extension,
                'mime_type' => $document->mime_type,
                'document_type' => $document->document_type,
                'documentable_type' => class_basename($document->documentable_type),
                'documentable_id' => $document->documentable_id,
                'uploaded_by' => $document->uploader->name,
                'uploaded_at' => $document->created_at->format('M d, Y'),
                'is_image' => $document->isImage(),
                'is_pdf' => $document->isPdf(),
                'is_held' => $canSeeHolds && array_key_exists($document->id, $holdIdByDocument),
                'legal_hold_id' => $canSeeHolds ? ($holdIdByDocument[$document->id] ?? null) : null,
            ];
        });

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'buildings' => $this->getBuildingsForFilter(),
            'filters' => $request->only(['type', 'model_type', 'search', 'building_id', 'wing_id']),
            // Phase-68 BULK-UI: client cap for the multi-select bulk-hold bar.
            'legal_hold_bulk_max' => $canSeeHolds ? (int) config('legal_hold.bulk_max', 100) : 0,
        ]);
    }

    /**
     * Upload a new document
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', new SecureFile(10, ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])],
            'title' => 'required|string|max:255',
            'document_type' => 'required|in:lease_agreement,tenant_id,tenant_passport,bank_statement,payslip,reference_letter,utility_bill,other',
            'documentable_type' => 'required|in:Lease,User',
            'documentable_id' => 'required|integer',
            'description' => 'nullable|string|max:1000',
        ]);

        $landlordId = $this->resolveLandlordId();

        // Verify documentable exists and belongs to landlord
        $modelClass = 'App\\Models\\'.$request->documentable_type;
        $documentable = $modelClass::findOrFail($request->documentable_id);

        // Authorization check
        if ($request->documentable_type === 'Lease') {
            if ($documentable->landlord_id !== $landlordId) {
                abort(403, 'Unauthorized to upload documents for this lease');
            }
        } elseif ($request->documentable_type === 'User') {
            // Can only upload documents for tenants belonging to this landlord
            if ($documentable->role !== 'tenant' || $documentable->landlord_id !== $landlordId) {
                abort(403, 'Unauthorized to upload documents for this user');
            }
        }

        // Handle file upload
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $sanitizedName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();

        // Create unique filename
        $fileName = time().'_'.$sanitizedName;

        // Store in private storage (path keyed to landlord, not uploader)
        $filePath = $file->storeAs(
            'documents/'.$landlordId.'/'.$request->documentable_type,
            $fileName,
            'local'
        );

        // Create document record
        $document = Document::create([
            'landlord_id' => $landlordId,
            'documentable_id' => $request->documentable_id,
            'documentable_type' => $modelClass,
            'title' => $request->title,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $request->document_type,
            'description' => $request->description,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded successfully');
    }

    /**
     * Download a document (with authorization)
     */
    public function download(Document $document)
    {
        // Authorization check
        $user = auth()->user();

        if ($user->role === 'landlord' && $document->landlord_id !== $user->id) {
            abort(403, 'Unauthorized to download this document');
        }

        if ($user->role === 'caretaker' && $document->landlord_id !== $user->landlord_id) {
            abort(403, 'Unauthorized to download this document');
        }

        if ($user->role === 'tenant') {
            // Tenants can only download their own documents
            if ($document->documentable_type === 'App\\Models\\User' && $document->documentable_id !== $user->id) {
                abort(403, 'Unauthorized to download this document');
            }
            if ($document->documentable_type === 'App\\Models\\Lease') {
                $lease = $document->documentable;
                if ($lease->tenant_id !== $user->id) {
                    abort(403, 'Unauthorized to download this document');
                }
            }
        }

        // Check if file exists
        if (! $document->fileExists()) {
            abort(404, 'File not found in storage');
        }

        // UPLOAD-8: file_name is the user-uploaded original filename and
        // ends up verbatim in the Content-Disposition response header.
        // Strip CR/LF and other characters that could be used to inject
        // additional headers, then ensure the extension is preserved
        // for sensible browser handling.
        $safeName = $this->sanitiseDownloadFilename($document->file_name, (string) $document->file_path);

        // Phase-59 ACCESS-AUDIT-2: PII audit trail. Fail-soft.
        app(\App\Services\Storage\FileAccessRecorder::class)->record(
            $user,
            $document,
            \App\Models\FileAccessAudit::ACTION_DOWNLOAD,
            request(),
            $document->file_path,
        );

        // Phase-59 SIGNED-URLS-2: 302 to a short-lived signed URL.
        // Browser fetches directly from s3 (presigned URL) or the
        // local-stream fallback (signed Laravel route). PHP-FPM no
        // longer streams the file bytes.
        return redirect()->away(
            app(\App\Services\Storage\TenantDiskResolver::class)
                ->temporaryUrl($document->file_path, $document->landlord_id, 5, $safeName),
        );
    }

    /**
     * UPLOAD-8: produce a download filename that is safe to inject into
     * the Content-Disposition response header. Stops CR/LF / control-byte
     * smuggling and falls back to a synthesised name if the original is
     * empty or all-stripped.
     */
    private function sanitiseDownloadFilename(?string $original, string $storedPath): string
    {
        $base = trim((string) $original);
        // Strip CR/LF/control bytes outright, plus quote characters that
        // can break out of the disposition string.
        $base = preg_replace('/[\x00-\x1F\x7F"\\\\;]+/u', '', $base) ?? '';
        // Disallow path separators in the user-facing name.
        $base = str_replace(['/', '\\'], '-', $base);
        $base = trim($base);

        if ($base === '' || $base === '.' || $base === '..') {
            $ext = pathinfo($storedPath, PATHINFO_EXTENSION);

            return 'document'.($ext ? '.'.$ext : '');
        }

        return mb_substr($base, 0, 200);
    }

    /**
     * View document inline (for PDFs and images)
     */
    public function view(Document $document)
    {
        // Same authorization as download
        $user = auth()->user();

        if ($user->role === 'landlord' && $document->landlord_id !== $user->id) {
            abort(403, 'Unauthorized to view this document');
        }

        if ($user->role === 'caretaker' && $document->landlord_id !== $user->landlord_id) {
            abort(403, 'Unauthorized to view this document');
        }

        if ($user->role === 'tenant') {
            if ($document->documentable_type === 'App\\Models\\User' && $document->documentable_id !== $user->id) {
                abort(403, 'Unauthorized to view this document');
            }
            if ($document->documentable_type === 'App\\Models\\Lease') {
                $lease = $document->documentable;
                if ($lease->tenant_id !== $user->id) {
                    abort(403, 'Unauthorized to view this document');
                }
            }
        }

        if (! $document->fileExists()) {
            abort(404, 'File not found in storage');
        }

        // Phase-59 ACCESS-AUDIT-2: PII audit trail. Fail-soft.
        app(\App\Services\Storage\FileAccessRecorder::class)->record(
            $user,
            $document,
            \App\Models\FileAccessAudit::ACTION_VIEW,
            request(),
            $document->file_path,
        );

        // Phase-59 SIGNED-URLS-2: 302 to short-lived signed URL with
        // inline disposition. Browser sets Content-Disposition: inline
        // via the signed-route param (local) or
        // Response-Content-Disposition (s3 presigned).
        return redirect()->away(
            app(\App\Services\Storage\TenantDiskResolver::class)
                ->temporaryUrl($document->file_path, $document->landlord_id, 5, $document->file_name, 'inline'),
        );
    }

    /**
     * Delete a document
     */
    public function destroy(Document $document)
    {
        // Caretakers can also delete documents owned by their landlord.
        $landlordId = $this->resolveLandlordId();
        if ($document->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized to delete this document');
        }

        // Phase-68 HOLD-GUARD-2: refuse before the irreversible deleteFile()
        // runs (the deleting observer fires on ->delete(), which is too late
        // here — the physical file would already be gone).
        if ($document->isHeld()) {
            throw new LegalHoldActiveException(Document::class, (int) $document->id);
        }

        // Delete the physical file
        $document->deleteFile();

        // Delete the record
        $document->delete();

        return back()->with('success', 'Document deleted successfully');
    }

    /**
     * Get documents for a specific documentable (AJAX endpoint)
     */
    public function forModel(Request $request)
    {
        $validated = $request->validate([
            'model_type' => 'required|in:Lease,User',
            'model_id' => ['required', 'integer', 'min:1'],
        ]);

        // VALID-10: resolve the class via match, never via string concat. The
        // user-provided model_type is already constrained by the validator
        // above, but we keep the second guard so an attacker can't ride a
        // future relaxation to instantiate arbitrary App\Models\* classes.
        $modelClass = match ($validated['model_type']) {
            'Lease' => \App\Models\Lease::class,
            'User' => \App\Models\User::class,
        };
        $model = $modelClass::findOrFail($validated['model_id']);

        $landlordId = $this->resolveLandlordId();

        // Authorization check
        if ($request->model_type === 'Lease' && $model->landlord_id !== $landlordId) {
            abort(403);
        }
        if ($request->model_type === 'User' && $model->role === 'tenant' && $model->landlord_id !== $landlordId) {
            abort(403);
        }

        $documents = $model->documents()->latest()->get()->map(function ($document) {
            return [
                'id' => $document->id,
                'title' => $document->title,
                'file_name' => $document->file_name,
                'file_size_formatted' => $document->file_size_formatted,
                'file_extension' => $document->file_extension,
                'document_type' => $document->document_type,
                'uploaded_at' => $document->created_at->format('M d, Y'),
                'is_image' => $document->isImage(),
                'is_pdf' => $document->isPdf(),
            ];
        });

        return response()->json(['documents' => $documents]);
    }
}
