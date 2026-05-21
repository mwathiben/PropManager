<?php

declare(strict_types=1);

namespace App\Services\Building;

use App\Models\Building;
use App\Models\BuildingAmenityDetail;
use Illuminate\Support\Facades\DB;

/**
 * Phase-78 AMENITY-DEPTH-1: persist per-amenity detail for a building. A detail
 * is only kept when its amenity_key is BOTH a known predefined key
 * (Building::getAllAmenityKeys) AND currently selected on the building — details
 * for unknown or deselected amenities are dropped so the table never drifts from
 * the building's amenity selection.
 */
class AmenityDetailService
{
    /**
     * @param  array<string, array{quantity?:mixed, provider?:mixed, account_ref?:mixed, monthly_cost_cents?:mixed}>  $details
     */
    public function sync(Building $building, array $details): void
    {
        $allowed = Building::getAllAmenityKeys();
        $selected = (array) ($building->amenities['selected'] ?? []);
        $valid = array_values(array_intersect($selected, $allowed));

        DB::transaction(function () use ($building, $details, $valid) {
            // Prune details for amenities no longer selected/valid.
            BuildingAmenityDetail::where('building_id', $building->id)
                ->whereNotIn('amenity_key', $valid !== [] ? $valid : ['__none__'])
                ->delete();

            foreach ($valid as $key) {
                if (! array_key_exists($key, $details)) {
                    continue;
                }
                $row = $details[$key];

                BuildingAmenityDetail::updateOrCreate(
                    ['building_id' => $building->id, 'amenity_key' => $key],
                    [
                        'landlord_id' => $building->landlord_id,
                        'quantity' => $this->intOrNull($row['quantity'] ?? null),
                        'provider' => $this->strOrNull($row['provider'] ?? null),
                        'account_ref' => $this->strOrNull($row['account_ref'] ?? null),
                        'monthly_cost_cents' => $this->intOrNull($row['monthly_cost_cents'] ?? null),
                    ],
                );
            }
        });
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    private function strOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
