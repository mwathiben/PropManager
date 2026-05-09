<?php

namespace App\Http\Controllers;

use App\Jobs\ExportUserData;
use App\Models\DeletionRequest;
use App\Services\DataDeletionService;
use App\Services\DataExportService;
use Illuminate\Http\Request;
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
        $expectedPrefix = "exports/{$userId}/";

        if (
            ! str_starts_with($path, $expectedPrefix)
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || str_contains($path, '\\')
        ) {
            abort(403, 'Unauthorized access');
        }

        $fullPath = realpath(storage_path("app/{$path}"));
        $allowedRoot = realpath(storage_path("app/exports/{$userId}"));

        if (! $fullPath || ! $allowedRoot || ! str_starts_with($fullPath, $allowedRoot.DIRECTORY_SEPARATOR)) {
            abort(404, 'Export not found or has expired');
        }

        if (! is_file($fullPath)) {
            abort(404, 'Export not found or has expired');
        }

        return response()->download($fullPath);
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
     * Immediate export (for small accounts, synchronous).
     */
    public function immediateExport(Request $request)
    {
        $user = $request->user();

        try {
            $zipPath = $this->exportService->exportUserData($user);
            $filename = basename($zipPath);

            return response()->download($zipPath, $filename)->deleteFileAfterSend(false);
        } catch (\Exception $e) {
            return back()->withErrors(['export' => 'Failed to generate export. Please try again.']);
        }
    }
}
