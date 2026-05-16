<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\DataIntegrityException;
use App\Models\Building;
use App\Models\Property;

/**
 * Phase-18 DATA-6: refuse to soft-delete a Property while it still has
 * live (non-soft-deleted) Buildings.
 *
 * Pre-Phase-18 the DB-level FK building.property_id was ON DELETE
 * CASCADE — but Eloquent's SoftDelete writes deleted_at on the
 * parent WITHOUT triggering DB cascade. So a soft-deleted Property
 * left its Buildings/Units/Leases all live. Dashboard queries that
 * read 'list my properties' applied the Property SoftDelete scope
 * and hid the row, but the 'list my buildings' query read live
 * Buildings — producing an operator-visible inconsistency.
 *
 * The conservative fix: require the operator to retire descendants
 * first. A portfolio-transfer workflow that needs cascading retirement
 * is a separate explicit method (out of scope for Phase 18).
 */
class PropertyObserver
{
    public function created(Property $property): void
    {
        // Phase-31 ONB-TTFI-1: first property = activation funnel step 2.
        if ($property->landlord_id !== null) {
            app(\App\Services\Onboarding\OnboardingMilestoneRecorder::class)
                ->record(
                    landlordId: (int) $property->landlord_id,
                    milestone: \App\Models\OnboardingMilestone::FIRST_PROPERTY,
                    metadata: ['property_id' => $property->id],
                );
        }
    }

    public function deleting(Property $property): void
    {
        $liveBuildings = Building::withoutGlobalScope('landlord')
            ->where('property_id', $property->id)
            ->whereNull('deleted_at')
            ->count();

        if ($liveBuildings > 0) {
            throw new DataIntegrityException(
                message: "Cannot delete Property {$property->id}: {$liveBuildings} live building(s) still reference it. Retire the buildings first.",
                errorCode: 'PROPERTY_DELETION_BLOCKED_BY_LIVE_BUILDINGS',
                context: ['property_id' => $property->id, 'live_building_count' => $liveBuildings],
            );
        }
    }
}
