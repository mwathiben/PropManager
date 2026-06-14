<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

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
     * Owns its transaction; returns true once the rebuild commits.
     */
    public function replaceForProperty(BuildingStructureSpec $spec): bool
    {
        return DB::transaction(function () use ($spec): bool {
            $this->clearExistingBuildings($spec->property);
            $this->build($spec);

            return true;
        });
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
