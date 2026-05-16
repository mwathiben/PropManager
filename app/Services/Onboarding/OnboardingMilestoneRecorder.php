<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Events\MilestoneRecorded;
use App\Models\OnboardingMilestone;

/**
 * Phase-31 ONB-TTFI-1: write-once recorder for activation milestones.
 * firstOrCreate keyed on (landlord_id, milestone) makes the recorder
 * idempotent — subsequent calls with the same landlord+milestone are
 * a no-op. Fires MilestoneRecorded on the FIRST write so downstream
 * listeners (LogMilestoneRecorded) can audit-trail the moment.
 *
 * Designed to be called from model observers (PropertyObserver
 * created, UnitObserver created, etc.) so the recorder never needs
 * to be sprinkled across controllers.
 */
class OnboardingMilestoneRecorder
{
    public function record(int $landlordId, string $milestone, array $metadata = []): OnboardingMilestone
    {
        if (! in_array($milestone, OnboardingMilestone::FUNNEL, true)) {
            throw new \InvalidArgumentException("Unknown milestone: {$milestone}");
        }

        $row = OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('milestone', $milestone)
            ->first();

        if ($row !== null) {
            return $row;
        }

        $row = OnboardingMilestone::create([
            'landlord_id' => $landlordId,
            'milestone' => $milestone,
            'reached_at' => now(),
            'metadata' => $metadata,
        ]);

        MilestoneRecorded::dispatch($row);

        return $row;
    }
}
