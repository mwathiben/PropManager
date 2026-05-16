<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Building;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase-29 WF-VACANCY-1: per-property + per-building occupancy
 * aggregation. Active lease (Lease.is_active=true on Unit) = occupied.
 * Returns 0.0 for buildings with zero units (no divide-by-zero).
 */
class OccupancyService
{
    /**
     * @return Collection<int, array{
     *     building_id: int,
     *     property_id: int,
     *     name: string,
     *     total_units: int,
     *     occupied_units: int,
     *     vacant_units: int,
     *     occupancy_rate_pct: float,
     *     target_occupancy_rate: float|null,
     *     is_below_target: bool
     * }>
     */
    public function byBuilding(User $landlord): Collection
    {
        return Building::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlord->id)
            ->with(['units.activeLease'])
            ->get()
            ->map(function (Building $building) {
                $total = $building->units->count();
                $occupied = $building->units->filter(fn ($u) => $u->activeLease !== null)->count();
                $rate = $total > 0 ? round(($occupied / $total) * 100, 2) : 0.0;
                $target = $building->target_occupancy_rate !== null
                    ? (float) $building->target_occupancy_rate
                    : null;

                return [
                    'building_id' => $building->id,
                    'property_id' => $building->property_id,
                    'name' => $building->name,
                    'total_units' => $total,
                    'occupied_units' => $occupied,
                    'vacant_units' => $total - $occupied,
                    'occupancy_rate_pct' => $rate,
                    'target_occupancy_rate' => $target,
                    'is_below_target' => $target !== null && $rate < $target,
                ];
            });
    }

    /**
     * Portfolio-level rate across every building the landlord owns.
     */
    public function portfolioRate(User $landlord): float
    {
        $rows = $this->byBuilding($landlord);
        $total = $rows->sum('total_units');
        if ($total === 0) {
            return 0.0;
        }

        return round(($rows->sum('occupied_units') / $total) * 100, 2);
    }
}
