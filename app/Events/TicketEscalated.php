<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-80 ESCALATION-2: fired when a caretaker (or the SLA-breach hook)
 * escalates a ticket to the landlord. NotifyLandlordOnTicketEscalation alerts
 * the owning landlord so they can acknowledge or reassign.
 */
class TicketEscalated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ?string $reason = null,
    ) {}
}
