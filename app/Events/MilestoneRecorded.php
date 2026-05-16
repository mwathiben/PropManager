<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\OnboardingMilestone;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Phase-31 ONB-TTFI-3: fired ONCE per (landlord, milestone) tuple by
 * OnboardingMilestoneRecorder::record when it inserts a new row.
 */
class MilestoneRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly OnboardingMilestone $milestone,
    ) {}
}
