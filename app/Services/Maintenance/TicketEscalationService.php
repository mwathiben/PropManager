<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Enums\TicketStatus;
use App\Events\TicketEscalated;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase-80 ESCALATION: a caretaker who is stuck escalates a ticket to the
 * landlord; the landlord acknowledges (or reassigns) to close the escalation.
 * Mirrors the vendor decline → notify-landlord flow (state + activity log +
 * event). An OPEN escalation is escalated_at set with escalation_acknowledged_at
 * still NULL.
 */
class TicketEscalationService
{
    private const TERMINAL = [TicketStatus::Resolved->value, TicketStatus::Closed->value, TicketStatus::Cancelled->value];

    /**
     * Escalate a ticket. $by is the caretaker raising it, or null for a
     * system escalation (SLA-breach auto-escalation). Idempotent: a ticket
     * already carrying an open escalation is returned unchanged.
     */
    public function escalate(Ticket $ticket, ?User $by, string $reason): Ticket
    {
        return DB::transaction(function () use ($ticket, $by, $reason) {
            $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->id);

            if (in_array($locked->status->value, self::TERMINAL, true)) {
                throw ValidationException::withMessages([
                    'ticket' => __('maintenance.escalation.not_open'),
                ]);
            }

            if ($locked->isEscalated()) {
                return $locked; // already escalated — no double-notify
            }

            $locked->update([
                'escalated_at' => now(),
                'escalated_by' => $by?->id,
                'escalation_reason' => $reason,
                'escalation_acknowledged_at' => null,
                'escalation_acknowledged_by' => null,
            ]);

            TicketActivity::create([
                'ticket_id' => $locked->id,
                'landlord_id' => $locked->landlord_id,
                'user_id' => $by?->id,
                'action' => TicketActivity::ACTION_ESCALATED,
                'description' => $reason,
                'created_at' => now(),
            ]);

            TicketEscalated::dispatch($locked->fresh(), $reason);

            return $locked->fresh();
        });
    }

    /**
     * Landlord acknowledges an open escalation (or it is cleared on reassign).
     * Idempotent: clearing a ticket with no open escalation is a no-op.
     */
    public function acknowledge(Ticket $ticket, User $landlord): Ticket
    {
        return DB::transaction(function () use ($ticket, $landlord) {
            $locked = Ticket::withoutGlobalScopes()->lockForUpdate()->findOrFail($ticket->id);

            if (! $locked->isEscalated()) {
                return $locked;
            }

            $locked->update([
                'escalation_acknowledged_at' => now(),
                'escalation_acknowledged_by' => $landlord->id,
            ]);

            TicketActivity::create([
                'ticket_id' => $locked->id,
                'landlord_id' => $locked->landlord_id,
                'user_id' => $landlord->id,
                'action' => TicketActivity::ACTION_ESCALATION_ACKNOWLEDGED,
                'description' => null,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
