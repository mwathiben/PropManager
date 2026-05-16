<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OccupancyTargetBreached;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Phase-29 WF-VACANCY-3: notify landlord when a building's occupancy
 * rate falls below the configured target. Phase-16 RESIL backoff for
 * transient delivery failures.
 */
class NotifyOnOccupancyTargetBreached implements ShouldQueue
{
    /** @var int */
    public $tries = 4;

    /** @var int[] */
    public $backoff = [30, 60, 300, 1800];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(OccupancyTargetBreached $event): void
    {
        $building = $event->building;

        $this->notifications->send(
            recipientId: $building->landlord_id,
            type: 'general',
            subject: __('workflow.occupancy.breach_subject', ['name' => $building->name]),
            message: __('workflow.occupancy.breach_body', [
                'name' => $building->name,
                'current' => number_format($event->currentRate, 1),
                'target' => number_format($event->targetRate, 1),
            ]),
            data: [
                'building_id' => $building->id,
                'current_rate' => $event->currentRate,
                'target_rate' => $event->targetRate,
            ],
            landlordId: $building->landlord_id,
        );
    }
}
