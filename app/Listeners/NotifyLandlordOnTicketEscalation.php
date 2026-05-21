<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketEscalated;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Phase-80 ESCALATION-2: in-app notification to the owning landlord when a
 * ticket is escalated, so they can acknowledge or reassign. Fail-soft.
 */
class NotifyLandlordOnTicketEscalation implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TicketEscalated $event): void
    {
        $ticket = $event->ticket;
        $landlordId = (int) $ticket->landlord_id;

        if ($landlordId === 0) {
            Log::info('NotifyLandlordOnTicketEscalation skipped — no landlord', ['ticket_id' => $ticket->id]);

            return;
        }

        $this->notifications->send(
            $landlordId,
            'maintenance_notice',
            __('maintenance.escalation.notify_subject'),
            __('maintenance.escalation.notify_body', ['title' => $ticket->title]),
            [
                'ticket_id' => $ticket->id,
                'reason' => $event->reason,
                'url' => route('tickets.show', $ticket->id),
            ],
            $landlordId,
        );
    }
}
