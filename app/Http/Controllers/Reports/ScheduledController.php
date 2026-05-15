<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
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
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SavedReport::class);

        $landlordId = $this->landlordIdFor($request);

        $schedules = ScheduledReport::query()
            ->where('landlord_id', $landlordId)
            ->with('savedReport:id,name,description')
            ->orderBy('next_due_at')
            ->get(['id', 'saved_report_id', 'cadence', 'recipient_email', 'next_due_at', 'last_sent_at']);

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

    public function destroy(Request $request, ScheduledReport $schedule): RedirectResponse
    {
        if ((int) $schedule->landlord_id !== $this->landlordIdFor($request)) {
            abort(403);
        }
        $schedule->delete();

        return redirect()->route('reports.scheduled.index')
            ->with('success', 'Schedule deleted.');
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
        $base = Carbon::now();

        return match ($cadence) {
            'weekly' => $base->copy()->addWeek(),
            'monthly' => $base->copy()->addMonth(),
            'quarterly' => $base->copy()->addMonths(3),
            default => $base->copy()->addWeek(),
        };
    }
}
