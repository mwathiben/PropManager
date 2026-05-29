<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Building;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-29 WF-VACANCY-3: fired by occupancy:audit when a building's
 * current occupancy rate falls below its configured
 * target_occupancy_rate. One-shot per month via Cache idempotency in
 * the dispatching command.
 */
class OccupancyTargetBreached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Building $building,
        public readonly float $currentRate,
        public readonly float $targetRate,
        public readonly CarbonImmutable $detectedAt,
    ) {}
}
