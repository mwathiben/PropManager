<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-28 TENANT-MAINT-3 / Phase-49 TICKETS-SLA-DEEP-3: fired by
 * tickets:audit-sla when a ticket passes sla_due_at (type='response')
 * or resolution_due_at (type='resolution') without satisfying the
 * respective gate. The listener (NotifyOnTicketSlaBreach) branches
 * messaging by $type so on-call sees response-vs-resolution distinct.
 */
class TicketSlaBreached
{
    use Dispatchable, SerializesModels;

    public const TYPE_RESPONSE = 'response';

    public const TYPE_RESOLUTION = 'resolution';

    public function __construct(
        public readonly Ticket $ticket,
        public readonly CarbonImmutable $breachedAt,
        public readonly string $type = self::TYPE_RESPONSE,
    ) {}
}
