<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketSlaBreached;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-28 TENANT-MAINT-3: notifies landlord + caretakers when a
 * ticket SLA is breached. Phase-16 RESIL pattern — exponential
 * backoff with 4 retries, final attempt dead-letters to failed_jobs.
 */
class NotifyOnTicketSlaBreach implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TicketSlaBreached $event): void
    {
        $ticket = $event->ticket;
        $landlordId = $ticket->landlord_id;

        // Landlord first.
        $this->notifications->send(
            recipientId: $landlordId,
            type: 'maintenance_notice',
            subject: __('tenant.ticket_sla.subject', ['title' => $ticket->title]),
            message: __('tenant.ticket_sla.body', [
                'title' => $ticket->title,
                'priority' => $ticket->priority,
                'breached_at' => $event->breachedAt->toDateTimeString(),
            ]),
            data: ['ticket_id' => $ticket->id, 'priority' => $ticket->priority],
            landlordId: $landlordId,
        );

        // Caretakers under the landlord.
        $caretakerIds = User::query()
            ->where('landlord_id', $landlordId)
            ->where('role', 'caretaker')
            ->pluck('id');

        foreach ($caretakerIds as $caretakerId) {
            $this->notifications->send(
                recipientId: $caretakerId,
                type: 'maintenance_notice',
                subject: __('tenant.ticket_sla.subject', ['title' => $ticket->title]),
                message: __('tenant.ticket_sla.body', [
                    'title' => $ticket->title,
                    'priority' => $ticket->priority,
                    'breached_at' => $event->breachedAt->toDateTimeString(),
                ]),
                data: ['ticket_id' => $ticket->id, 'priority' => $ticket->priority],
                landlordId: $landlordId,
            );
        }
    }
}
