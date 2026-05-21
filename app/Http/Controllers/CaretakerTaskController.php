<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\Ticket\EscalateTicketRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\Maintenance\TicketEscalationService;
use App\Services\Water\WaterModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-80 TASK-BOARD: the caretaker's mobile-first daily board — their OPEN
 * assigned tickets grouped by urgency, with inline status transitions and a
 * stuck → landlord escalation. Strictly the caretaker's OWN assigned tickets.
 */
class CaretakerTaskController extends Controller
{
    use WithLandlordScope;

    /** Forward-only transition order. */
    private const ORDER = ['open' => 0, 'acknowledged' => 1, 'in_progress' => 2, 'resolved' => 3];

    public function index(): Response
    {
        $caretakerId = (int) auth()->id();
        $landlordId = $this->getLandlordId();

        $tickets = Ticket::query()
            ->where('assigned_to', $caretakerId)
            ->open()
            ->with(['building:id,name', 'unit:id,unit_number', 'reporter:id,name'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('created_at')
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'priority' => $t->priority,
                'status' => $t->status->value,
                'building' => $t->building?->name,
                'unit' => $t->unit?->unit_number,
                'reporter' => $t->reporter?->name,
                'created_at' => $t->created_at?->toIso8601String(),
                'is_overdue' => $t->resolution_due_at !== null
                    && $t->resolution_due_at->isPast()
                    && $t->resolved_at === null,
                'is_escalated' => $t->isEscalated(),
            ])
            ->values();

        return Inertia::render('Caretaker/TaskBoard', [
            'tasks' => $tickets,
            'waterEnabled' => WaterModuleAccess::enabledForLandlord($landlordId),
            'escalationReasons' => (array) config('maintenance.escalation_reasons', []),
        ]);
    }

    /**
     * Phase-80 TASK-BOARD-2: inline forward status transition, assignee-only.
     */
    public function transition(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeAssignee($ticket);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                TicketStatus::Acknowledged->value,
                TicketStatus::InProgress->value,
                TicketStatus::Resolved->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = $ticket->status->value;
        $target = $validated['status'];
        if ((self::ORDER[$target] ?? -1) <= (self::ORDER[$current] ?? -1)) {
            throw ValidationException::withMessages(['status' => __('maintenance.task_board.invalid_transition')]);
        }

        $attrs = ['status' => $target];
        if ($target === TicketStatus::Resolved->value) {
            $attrs['resolved_at'] = now();
            $attrs['resolution_notes'] = $validated['notes'] ?? null;
        }
        $ticket->update($attrs);

        TicketActivity::create([
            'ticket_id' => $ticket->id,
            'landlord_id' => $ticket->landlord_id,
            'user_id' => auth()->id(),
            'action' => $target === TicketStatus::Resolved->value
                ? TicketActivity::ACTION_RESOLVED
                : TicketActivity::ACTION_STATUS_CHANGED,
            'old_value' => $current,
            'new_value' => $target,
            'description' => $validated['notes'] ?? null,
            'created_at' => now(),
        ]);

        return back()->with('success', __('maintenance.task_board.updated'));
    }

    /**
     * Phase-80 ESCALATION-2: caretaker escalates a stuck ticket to the landlord.
     */
    public function escalate(EscalateTicketRequest $request, Ticket $ticket, TicketEscalationService $escalation): RedirectResponse
    {
        $validated = $request->validated();
        $presets = (array) config('maintenance.escalation_reasons', []);
        $reason = isset($validated['preset'], $presets[$validated['preset']])
            ? $presets[$validated['preset']].' — '.$validated['reason']
            : $validated['reason'];

        $escalation->escalate($ticket, $request->user(), $reason);

        return back()->with('success', __('maintenance.escalation.raised'));
    }

    private function authorizeAssignee(Ticket $ticket): void
    {
        abort_unless(
            auth()->user()->isCaretaker() && (int) $ticket->assigned_to === (int) auth()->id(),
            403,
        );
    }
}
