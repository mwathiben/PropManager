<?php

namespace App\Observers;

use App\Models\Building;
use App\Services\BuildingCacheService;

class BuildingObserver
{
    public function created(Building $building): void
    {
        $this->invalidateCache($building);
    }

    public function updated(Building $building): void
    {
        $this->invalidateCache($building);
    }

    public function deleted(Building $building): void
    {
        $this->invalidateCache($building);
    }

    private function invalidateCache(Building $building): void
    {
        if ($building->landlord_id) {
            BuildingCacheService::invalidateBuilding($building);
        }
    }
}
