<?php

namespace App\Observers;

use App\Models\Unit;
use App\Services\BuildingCacheService;

class UnitObserver
{
    public function created(Unit $unit): void
    {
        $this->invalidateBuildingCache($unit);
    }

    public function updated(Unit $unit): void
    {
        $this->invalidateBuildingCache($unit);
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
