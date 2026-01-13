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
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = base64_decode($request->path);

        // Security: Ensure the path belongs to this user
        if (! str_contains($path, "exports/{$request->user()->id}/")) {
            abort(403, 'Unauthorized access');
        }

        $fullPath = storage_path("app/{$path}");

        if (! file_exists($fullPath)) {
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
