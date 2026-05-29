<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MoveOut;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-29 WF-VACANCY-2: fired when a MoveOut transitions to
 * Completed and no future-dated Lease exists for the unit.
 */
class VacancyDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Unit $unit,
        public readonly MoveOut $moveOut,
        public readonly CarbonImmutable $vacatedAt,
    ) {}
}
