<?php

namespace App\Traits;

use App\Models\Building;
use Illuminate\Database\Eloquent\Builder;

trait HasBuildingFilter
{
    /**
     * Get building IDs to filter by (includes wings if building selected).
     */
    protected function getBuildingIds(?int $buildingId, ?int $wingId): array
    {
        if ($wingId) {
            return [$wingId];
        }

        if ($buildingId) {
            $building = Building::find($buildingId);

            return $building
                ? $building->wings->pluck('id')->push($buildingId)->toArray()
                : [$buildingId];
        }

        return [];
    }

    /**
     * Get buildings list for filter dropdown (main buildings with wings eager loaded).
     */
    protected function getBuildingsForFilter(): \Illuminate\Database\Eloquent\Collection
    {
        return Building::where('landlord_id', auth()->id())
            ->whereNull('parent_building_id')
            ->with('wings:id,name,parent_building_id')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Apply building filter to a query via unit relationship.
     */
    protected function applyBuildingFilterViaUnit(Builder $query, ?int $buildingId, ?int $wingId): Builder
    {
        $buildingIds = $this->getBuildingIds($buildingId, $wingId);

        if (empty($buildingIds)) {
            return $query;
        }

        return $query->whereHas('unit', fn ($q) => $q->whereIn('building_id', $buildingIds));
    }

    /**
     * Apply building filter to a query via lease->unit relationship.
     */
    protected function applyBuildingFilterViaLease(Builder $query, ?int $buildingId, ?int $wingId): Builder
    {
        $buildingIds = $this->getBuildingIds($buildingId, $wingId);

        if (empty($buildingIds)) {
            return $query;
        }

        return $query->whereHas('lease.unit', fn ($q) => $q->whereIn('building_id', $buildingIds));
    }

    /**
     * Apply building filter directly to a query with building_id column.
     */
    protected function applyBuildingFilterDirect(Builder $query, ?int $buildingId, ?int $wingId): Builder
    {
        $buildingIds = $this->getBuildingIds($buildingId, $wingId);

        if (empty($buildingIds)) {
            return $query;
        }

        return $query->whereIn('building_id', $buildingIds);
    }
}
