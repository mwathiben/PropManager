<?php

namespace App\Observers;

use App\Exceptions\DataIntegrityException;
use App\Models\Lease;
use App\Models\Unit;
use App\Services\BuildingCacheService;

class UnitObserver
{
    public function created(Unit $unit): void
    {
        $this->invalidateBuildingCache($unit);

        // Phase-31 ONB-TTFI-1: first unit = activation funnel step 3.
        if ($unit->landlord_id !== null) {
            app(\App\Services\Onboarding\OnboardingMilestoneRecorder::class)
                ->record(
                    landlordId: (int) $unit->landlord_id,
                    milestone: \App\Models\OnboardingMilestone::FIRST_UNIT,
                    metadata: ['unit_id' => $unit->id],
                );
        }
    }

    public function updated(Unit $unit): void
    {
        $this->invalidateBuildingCache($unit);
    }

    /**
     * Phase-18 DATA-3: refuse to soft/force-delete a Unit while an
     * active Lease still references it. Pre-fix the Lease was left
     * pointing at a soft-deleted Unit — Eloquent's global SoftDelete
     * scope on Unit then dropped the lease from joined dashboard
     * queries, producing operator-visible 'lease without unit' bugs.
     *
     * Use-case: an operator wanting to soft-delete a Unit must first
     * terminate any active Lease (the LeaseController has an
     * explicit terminate flow). Force-delete (Phase-18 AUTHZ-9
     * Policy method) inherits the same guard.
     */
    public function deleting(Unit $unit): void
    {
        $activeCount = Lease::withoutGlobalScope('landlord')
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->count();

        if ($activeCount > 0) {
            throw new DataIntegrityException(
                message: "Cannot delete Unit {$unit->id}: {$activeCount} active lease(s) still reference it. Terminate the leases first.",
                errorCode: 'UNIT_DELETION_BLOCKED_BY_ACTIVE_LEASE',
                context: ['unit_id' => $unit->id, 'active_lease_count' => $activeCount],
            );
        }
    }

    public function deleted(Unit $unit): void
    {
        $this->invalidateBuildingCache($unit);
    }

    private function invalidateBuildingCache(Unit $unit): void
    {
        if ($unit->landlord_id && $unit->building_id) {
            BuildingCacheService::invalidateBuildingById($unit->landlord_id, $unit->building_id);
        }
    }
}
