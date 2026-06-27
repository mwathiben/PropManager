<?php

namespace App\Http\Controllers;

use App\Jobs\ExportUserData;
use App\Models\AuditLog;
use App\Models\DeletionRequest;
use App\Services\DataDeletionService;
use App\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GdprController extends Controller
{
    public function __construct(
        protected DataExportService $exportService,
        protected DataDeletionService $deletionService
    ) {}

    /**
     * Show privacy settings page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $deletionStatus = $this->deletionService->getDeletionStatus($user);
        $canDelete = $this->deletionService->canRequestDeletion($user);

        return Inertia::render('Settings/Privacy', [
            'deletionStatus' => $deletionStatus ? [
                'id' => $deletionStatus->id,
                'status' => $deletionStatus->status,
                'requested_at' => $deletionStatus->requested_at->format('F j, Y'),
                'scheduled_deletion_at' => $deletionStatus->scheduled_deletion_at->format('F j, Y'),
                'days_remaining' => $deletionStatus->days_remaining,
            ] : null,
            'canDelete' => $canDelete,
            'gracePeriodDays' => config('security.compliance.deletion_grace_days', 30),
        ]);
    }

    /**
     * Request data export (GDPR Article 20).
     */
    public function requestExport(Request $request)
    {
        $user = $request->user();

        // Queue the export job
        ExportUserData::dispatch($user, sendEmail: true);

        return back()->with('success', 'Your data export has been queued. You will receive an email when it\'s ready.');
    }

    /**
     * Download data export.
     */
    public function downloadExport(Request $request)
    {
        // VALID-4: the previous str_contains check on a base64-decoded
        // path was satisfied by any substring, so a path like
        // `../../../../etc/exports/{userId}/passwd` passed and `storage_path("app/{$path}")`
        // resolved to an arbitrary location, granting authenticated arbitrary
        // file read across the entire storage/app filesystem. Now:
        //   - reject any input containing `..`, null bytes, or path separators
        //     other than forward-slash (Windows backslash on Linux storage),
        //   - require the path to *start with* the user's exports/{id}/ prefix
        //     (str_starts_with, not str_contains),
        //   - resolve the absolute path with realpath() and verify it's still
        //     a descendant of storage_path('app/exports/{userId}/').
        $request->validate([
            'path' => 'required|string|max:1024',
        ]);

        $path = base64_decode($request->path, true);
        if ($path === false) {
            abort(400, 'Invalid path encoding.');
        }

        $userId = (int) $request->user()->id;
        $this->assertExportPathAuthorized($path, $userId);

        // Phase-59 PATH-CAVEAT-2: switch from absolute-path
        // response()->download to a tenant-disk signed URL so the
        // controller works across local + s3. The traversal guards
        // above (str_starts_with('exports/{userId}/') + reject '..' +
        // null bytes + backslashes) already constrain $path to the
        // user's export subtree — the previous realpath() chain was
        // local-driver-only defense in depth.
        if (! Storage::tenant()->exists($path)) {
            abort(404, 'Export not found or has expired');
        }

        return redirect()->away(
            app(\App\Services\Storage\TenantDiskResolver::class)
                ->temporaryUrl($path, $request->user()->landlord_id ?? $userId, 5, basename($path)),
        );
    }

    /**
     * Abort with 403 if $path is not safely within the user's export subtree.
     * Checks: correct prefix, no traversal sequences, no null bytes, no backslashes.
     */
    private function assertExportPathAuthorized(string $path, int $userId): void
    {
        $expectedPrefix = "exports/{$userId}/";

        if (
            ! str_starts_with($path, $expectedPrefix)
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Request account deletion (GDPR Article 17).
     */
    public function requestDeletion(Request $request)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000',
            'confirm' => 'required|accepted',
        ]);

        $user = $request->user();

        // Check if deletion is allowed
        $canDelete = $this->deletionService->canRequestDeletion($user);
        if (! $canDelete['can_delete']) {
            return back()->withErrors(['deletion' => $canDelete['blockers']]);
        }

        // Create deletion request
        $deletionRequest = $this->deletionService->requestDeletion($user, $request->reason);

        return back()->with('success',
            "Your account deletion has been scheduled for {$deletionRequest->scheduled_deletion_at->format('F j, Y')}. ".
            'You can cancel this request within the grace period.'
        );
    }

    /**
     * Cancel deletion request.
     */
    public function cancelDeletion(Request $request)
    {
        $user = $request->user();

        $deletionRequest = DeletionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->deletionService->cancelDeletion($deletionRequest);

        return back()->with('success', 'Your deletion request has been cancelled.');
    }

    /**
     * Phase-13 DPA-4: Article 18 / Kenya DPA Section 26(d). Mark the
     * account as restricted — read-only mode active from now on. The
     * AuthServiceProvider Gate::before hook denies write-side
     * abilities while restricted; the controller's only job is to
     * stamp the timestamp + reason and write the audit row.
     */
    public function requestRestriction(Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        if ($user->isRestricted()) {
            return back()->with('success', 'Your account is already restricted.');
        }

        $user->forceFill([
            'restricted_at' => now(),
            'restriction_reason' => $validated['reason'],
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'landlord_id' => $user->effectiveScopeIdOrNull(),
            'event_type' => 'processing_restricted',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'metadata' => [
                'reason' => $validated['reason'],
                'compliance' => 'gdpr_article_18',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Your account has been placed under restricted-processing mode. Write actions are paused until you release the restriction.');
    }

    /**
     * Phase-13 DPA-4 release path: clear the restriction. Releases
     * are user-initiated; an operator-initiated release would go
     * through an admin route (not in this commit's scope).
     */
    public function releaseRestriction(Request $request)
    {
        $user = $request->user();
        if (! $user->isRestricted()) {
            return back()->with('success', 'Your account is not under restriction.');
        }

        $previousReason = $user->restriction_reason;

        $user->forceFill([
            'restricted_at' => null,
            'restriction_reason' => null,
        ])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'landlord_id' => $user->effectiveScopeIdOrNull(),
            'event_type' => 'processing_restriction_released',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'metadata' => [
                'previous_reason' => $previousReason,
                'compliance' => 'gdpr_article_18',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Your account restriction has been released. Normal processing has resumed.');
    }

    /**
     * Immediate export (for small accounts, synchronous).
     */
    public function immediateExport(Request $request)
    {
        $user = $request->user();

        try {
            // Phase-59 PATH-CAVEAT-2: DataExportService now returns the
            // tenant-disk-relative path (was absolute). Use the
            // signed-URL resolver so the response is driver-agnostic.
            $relativeZipPath = $this->exportService->exportUserData($user);
            $filename = basename($relativeZipPath);

            return redirect()->away(
                app(\App\Services\Storage\TenantDiskResolver::class)
                    ->temporaryUrl($relativeZipPath, $user->landlord_id ?? $user->id, 5, $filename),
            );
        } catch (\Exception $e) {
            return back()->withErrors(['export' => 'Failed to generate export. Please try again.']);
        }
    }
}
