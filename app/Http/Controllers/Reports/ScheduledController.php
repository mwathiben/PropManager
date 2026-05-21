<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Services\Reports\ReportBuilderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-27 BI-DELIVERY-3: scheduled-reports self-serve UI.
 *
 * Authorization runs through SavedReportPolicy (a scheduled_reports row
 * piggybacks on its saved_report — only the owning landlord can
 * schedule). Recipient validation enforces Phase-13 PERSONAL-DATA-1:
 * only the landlord's own email + known caretaker addresses.
 */
class ScheduledController extends Controller
{
    public function __construct(
        private ReportBuilderService $builder,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SavedReport::class);

        $landlordId = $this->landlordIdFor($request);

        $schedules = ScheduledReport::query()
            ->where('landlord_id', $landlordId)
            ->with('savedReport:id,name,description')
            ->orderBy('next_due_at')
            ->get(['id', 'saved_report_id', 'cadence', 'recipient_email', 'next_due_at', 'last_sent_at', 'paused_at']);

        $savedReports = SavedReport::query()
            ->where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $allowedRecipients = $this->allowedRecipientsFor($landlordId);

        return Inertia::render('Reports/Scheduled', [
            'schedules' => $schedules,
            'savedReports' => $savedReports,
            'cadences' => ScheduledReport::CADENCES,
            'allowedRecipients' => $allowedRecipients,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SavedReport::class);

        $landlordId = $this->landlordIdFor($request);

        $validated = $request->validate([
            'saved_report_id' => ['required', 'integer', Rule::exists('saved_reports', 'id')->where('landlord_id', $landlordId)],
            'cadence' => ['required', Rule::in(ScheduledReport::CADENCES)],
            'recipient_email' => ['required', 'email'],
        ]);

        // Phase-13 PERSONAL-DATA-1: lock recipient to landlord's own
        // email + their caretaker addresses. Third-party emails would
        // turn this into a data-exfiltration vector.
        $allowed = $this->allowedRecipientsFor($landlordId);
        if (! in_array(strtolower($validated['recipient_email']), array_map('strtolower', $allowed), true)) {
            throw ValidationException::withMessages([
                'recipient_email' => 'Recipient must be your own email or a caretaker on your account.',
            ]);
        }

        ScheduledReport::create([
            'landlord_id' => $landlordId,
            'saved_report_id' => $validated['saved_report_id'],
            'cadence' => $validated['cadence'],
            'recipient_email' => $validated['recipient_email'],
            'next_due_at' => $this->nextDueAtFor($validated['cadence']),
        ]);

        return redirect()->route('reports.scheduled.index')
            ->with('success', 'Schedule created.');
    }

    /**
     * Phase-73 SCHEDULED-DEPTH: edit cadence + recipient in place. The
     * recipient stays inside the Phase-13 allow-list; changing the cadence
     * recomputes next_due_at from now() so the new interval takes effect
     * from the edit, not from a stale schedule point.
     */
    public function update(Request $request, ScheduledReport $schedule): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);
        if ((int) $schedule->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validate([
            'cadence' => ['required', Rule::in(ScheduledReport::CADENCES)],
            'recipient_email' => ['required', 'email'],
        ]);

        $allowed = $this->allowedRecipientsFor($landlordId);
        if (! in_array(strtolower($validated['recipient_email']), array_map('strtolower', $allowed), true)) {
            throw ValidationException::withMessages([
                'recipient_email' => 'Recipient must be your own email or a caretaker on your account.',
            ]);
        }

        $attributes = [
            'cadence' => $validated['cadence'],
            'recipient_email' => $validated['recipient_email'],
        ];

        // Only re-anchor next_due_at when the cadence actually changed —
        // editing the recipient alone must not slide the next send.
        if ($validated['cadence'] !== $schedule->cadence) {
            $attributes['next_due_at'] = $this->nextDueAtFor($validated['cadence']);
        }

        $schedule->update($attributes);

        return redirect()->route('reports.scheduled.index')
            ->with('success', 'Schedule updated.');
    }

    /**
     * Phase-73 SCHEDULED-DEPTH: pause/resume without deleting. The send
     * cron skips paused rows; resuming re-anchors next_due_at from now()
     * so a paused stretch never fires a backlog of catch-up sends.
     */
    public function togglePause(Request $request, ScheduledReport $schedule): RedirectResponse
    {
        if ((int) $schedule->landlord_id !== $this->landlordIdFor($request)) {
            abort(403);
        }

        if ($schedule->paused_at === null) {
            $schedule->update(['paused_at' => now()]);
            $message = 'Schedule paused.';
        } else {
            $schedule->update([
                'paused_at' => null,
                'next_due_at' => $this->nextDueAtFor($schedule->cadence),
            ]);
            $message = 'Schedule resumed.';
        }

        return redirect()->route('reports.scheduled.index')
            ->with('success', $message);
    }

    public function destroy(Request $request, ScheduledReport $schedule): RedirectResponse
    {
        if ((int) $schedule->landlord_id !== $this->landlordIdFor($request)) {
            abort(403);
        }
        $schedule->delete();

        return redirect()->route('reports.scheduled.index')
            ->with('success', 'Schedule deleted.');
    }

    /**
     * Phase-50 REAL-TIME-PREVIEW-2: ad-hoc run of a saved report from
     * the scheduled-reports surface so the landlord sees the same rows
     * the next mail will carry. Strict ownership — the saved_report_id
     * MUST belong to the calling landlord; cross-tenant ids 403.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SavedReport::class);

        $landlordId = $this->landlordIdFor($request);

        $validated = $request->validate([
            'saved_report_id' => ['required', 'integer'],
        ]);

        $report = SavedReport::query()
            ->where('id', $validated['saved_report_id'])
            ->where('landlord_id', $landlordId)
            ->first(['id', 'name', 'config']);

        if (! $report) {
            abort(403, 'Saved report does not belong to this landlord.');
        }

        try {
            $rows = $this->builder->run($report->config, $landlordId);
        } catch (\Throwable $e) {
            // Phase-53 GAUGE-WIRING-2: count preview failures distinct
            // from the builder's own surface label so dashboard, scheduled,
            // and builder paths each surface separately in ops.
            try {
                app(\App\Services\MetricsService::class)
                    ->increment('report_render_failure_count', 1, ['surface' => 'scheduled']);
            } catch (\Throwable) {
                // best-effort
            }
            throw $e;
        }

        return response()->json([
            'report_id' => $report->id,
            'report_name' => $report->name,
            'rows' => $rows,
            'previewed_at' => now()->toIso8601String(),
        ]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }

    /**
     * @return list<string>
     */
    private function allowedRecipientsFor(int $landlordId): array
    {
        $landlord = User::find($landlordId);
        if (! $landlord) {
            return [];
        }

        $caretakers = User::where('landlord_id', $landlordId)
            ->where('role', 'caretaker')
            ->pluck('email')
            ->all();

        return array_values(array_unique(array_filter(array_merge([$landlord->email], $caretakers))));
    }

    private function nextDueAtFor(string $cadence): Carbon
    {
        return ScheduledReport::nextDueAtForCadence($cadence);
    }
}
