<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketSlaBreached;
use App\Models\User;
use App\Services\Maintenance\TicketEscalationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-80 ESCALATION-4: opt-in. A resolution-SLA breach on a caretaker-assigned
 * ticket is exactly "stuck" — auto-escalate it so the landlord has a single
 * escalation queue. Off by default (config maintenance.auto_escalate_on_sla_breach).
 * Idempotent via TicketEscalationService (no double-escalate).
 */
class AutoEscalateOnSlaBreach implements ShouldQueue
{
    public function __construct(private readonly TicketEscalationService $escalation) {}

    public function handle(TicketSlaBreached $event): void
    {
        if (! config('maintenance.auto_escalate_on_sla_breach', false)) {
            return;
        }

        if ($event->type !== TicketSlaBreached::TYPE_RESOLUTION) {
            return;
        }

        $ticket = $event->ticket;
        $assignee = $ticket->assigned_to !== null ? User::find($ticket->assigned_to) : null;
        if ($assignee === null || ! $assignee->isCaretaker()) {
            return; // only caretaker-owned tickets escalate to the landlord
        }

        if ($ticket->isEscalated()) {
            return;
        }

        // System escalation (no caretaker actor) — the breach itself is the trigger.
        $this->escalation->escalate($ticket, null, __('maintenance.escalation.sla_breach_reason'));
    }
}
