<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Persists the building/unit structure for a property from a BuildingStructureSpec.
 *
 * The main building is always created from the spec's main block; for a single
 * building that block carries the grid, while a winged property zeroes it so the
 * main row acts as a parent container that owns no units and each wing is created
 * with its own grid beneath it. Callers run this inside their own transaction.
 */
final class BuildingStructureBuilder
{
    public function build(BuildingStructureSpec $spec): void
    {
        $main = $this->createBuilding($spec, $spec->mainBlock, null);
        $this->generateUnits($main, $spec->mainBlock, $spec);

        foreach ($spec->wings as $wing) {
            $wingBuilding = $this->createBuilding($spec, $wing, $main->id);
            $this->generateUnits($wingBuilding, $wing, $spec);
        }
    }

    /**
     * Replace the property's structure in place: a re-submitted structure step
     * force-deletes the existing buildings/units and rebuilds from the spec.
     *
     * Fail-closed — when those units already carry leases or water readings the
     * rebuild is refused with a ValidationException carrying an actionable
     * message (and a logged warning) so a re-edit can never silently destroy
     * tenancy or billing history. Returns true once the rebuild commits; the
     * deleted-unit count is logged. Owns its transaction so the guard rejects
     * before any write begins.
     */
    public function replaceForProperty(BuildingStructureSpec $spec): bool
    {
        $property = $spec->property;
        $existingUnitIds = $this->existingUnitIds($property);

        $this->guardAgainstHistoryLoss($property, $existingUnitIds);

        return DB::transaction(function () use ($spec, $property, $existingUnitIds): bool {
            $this->clearExistingBuildings($property);
            $this->build($spec);

            if ($existingUnitIds->isNotEmpty()) {
                Log::info('Onboarding structure replaced', [
                    'landlord_id' => $property->landlord_id,
                    'property_id' => $property->id,
                    'units_deleted' => $existingUnitIds->count(),
                ]);
            }

            return true;
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function existingUnitIds(Property $property): Collection
    {
        return Unit::where('landlord_id', $property->landlord_id)
            ->whereHas('building', fn ($query) => $query->where('property_id', $property->id))
            ->pluck('id');
    }

    /**
     * Refuse a rebuild when the property's existing units carry irreplaceable
     * history. The rebuild force-deletes units, and water_connections.unit_id is
     * nullOnDelete — so a water connection (and its restrictOnDelete invoices) is
     * silently detached even when the unit has no lease and no readings. The scope
     * is therefore leases + water readings + water connections: leases anchor
     * tenancy and lease-backed invoices, while a water connection anchors
     * water-client billing that does NOT hang off a lease. Loosely-attached
     * records (unit documents, building tickets) are intentionally NOT gated, to
     * avoid false-positive blocks on an otherwise-empty structure.
     *
     * @param  Collection<int, int>  $unitIds
     *
     * @throws ValidationException when those records exist.
     */
    private function guardAgainstHistoryLoss(Property $property, Collection $unitIds): void
    {
        if ($unitIds->isEmpty()) {
            return;
        }

        $leaseCount = Lease::whereIn('unit_id', $unitIds)->count();
        $readingCount = WaterReading::whereIn('unit_id', $unitIds)->count();
        $connectionCount = WaterConnection::whereIn('unit_id', $unitIds)->count();

        if ($leaseCount === 0 && $readingCount === 0 && $connectionCount === 0) {
            return;
        }

        Log::warning('Onboarding structure replace blocked: units have leases, water readings, or water connections', [
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->id,
            'units' => $unitIds->count(),
            'leases' => $leaseCount,
            'water_readings' => $readingCount,
            'water_connections' => $connectionCount,
        ]);

        throw ValidationException::withMessages([
            'error' => __('onboarding.page.structure.rebuild_blocked'),
        ]);
    }

    private function clearExistingBuildings(Property $property): void
    {
        $existing = Building::where('property_id', $property->id)
            ->where('landlord_id', $property->landlord_id)
            ->get();

        foreach ($existing as $building) {
            $building->units()->forceDelete();
            $building->forceDelete();
        }
    }

    private function createBuilding(BuildingStructureSpec $spec, UnitBlockSpec $block, ?int $parentId): Building
    {
        return Building::create([
            'property_id' => $spec->property->id,
            'landlord_id' => $spec->property->landlord_id,
            'parent_building_id' => $parentId,
            'name' => $block->name,
            'unit_prefix' => $block->prefix !== '' ? $block->prefix : null,
            'total_floors' => $block->floors,
            'units_per_floor' => $block->unitsPerFloor,
            'is_wing' => $parentId !== null,
        ]);
    }

    private function generateUnits(Building $building, UnitBlockSpec $block, BuildingStructureSpec $spec): void
    {
        for ($floor = 1; $floor <= $block->floors; $floor++) {
            for ($position = 1; $position <= $block->unitsPerFloor; $position++) {
                Unit::create([
                    'landlord_id' => $spec->property->landlord_id,
                    'building_id' => $building->id,
                    'floor_number' => $floor,
                    'unit_number' => $block->prefix.(($floor * 100) + $position),
                    'status' => 'vacant',
                    'target_rent' => $spec->baseRent,
                ]);
            }
        }
    }
}
