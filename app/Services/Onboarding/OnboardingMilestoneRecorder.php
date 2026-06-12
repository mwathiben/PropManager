<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Events\MilestoneRecorded;
use App\Models\OnboardingMilestone;
use App\Models\User;

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
 *
 * Phase-38 DEFER-TEST-HEALTH-1/3: returns null when the landlord
 * User row doesn't exist — protects against FK violations when a
 * Payment / Property / Invoice with a stale landlord_id is created
 * (legitimate in delete-cascading edge cases and test fixtures
 * that haven't seeded the User row). Consistent with TenantScope's
 * defensive-soft pattern.
 */
class OnboardingMilestoneRecorder
{
    public function record(int $landlordId, string $milestone, array $metadata = []): ?OnboardingMilestone
    {
        if (! in_array($milestone, OnboardingMilestone::FUNNEL, true)) {
            throw new \InvalidArgumentException("Unknown milestone: {$milestone}");
        }

        if (! User::query()->whereKey($landlordId)->exists()) {
            return null;
        }

        // Phase-38 DEFER-TEST-HEALTH-1/3: firstOrCreate is NOT
        // atomic in Laravel (SELECT then INSERT), so a race between
        // concurrent observers — e.g. landlord-creation
        // signed_up + a near-simultaneous first-tenant signup that
        // touches the same milestone — can still throw
        // UniqueConstraintViolationException. Catch + reload makes
        // the recorder truly idempotent.
        try {
            // Legitimate cross-landlord write: this records a milestone for
            // $landlordId regardless of who is authenticated (observers fire
            // under a tenant's session, a sibling landlord's, or none), so
            // opt out of TenantScope's always-overwrite landlord_id guard.
            $row = OnboardingMilestone::withoutLandlordOverride(
                fn () => OnboardingMilestone::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'landlord_id' => $landlordId,
                        'milestone' => $milestone,
                    ],
                    [
                        'reached_at' => now(),
                        'metadata' => $metadata,
                    ],
                ),
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $row = OnboardingMilestone::query()
                ->withoutGlobalScopes()
                ->where('landlord_id', $landlordId)
                ->where('milestone', $milestone)
                ->firstOrFail();
        }

        if ($row->wasRecentlyCreated) {
            MilestoneRecorded::dispatch($row);
        }

        return $row;
    }
}
