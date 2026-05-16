<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-28 TENANT-MAINT-3: fired by tickets:audit-sla when a ticket
 * passes its sla_due_at without first_response_at. The listener
 * (NotifyOnTicketSlaBreach) handles notification fan-out under
 * Phase-16 RESIL backoff.
 */
class TicketSlaBreached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly CarbonImmutable $breachedAt,
    ) {
    }
}
