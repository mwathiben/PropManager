<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => 'Block '.fake()->randomLetter(),
            'total_floors' => fake()->numberBetween(1, 10),
            'units_per_floor' => fake()->numberBetween(2, 8),
            'building_type' => fake()->randomElement(array_keys(Building::BUILDING_TYPES)),
            'landlord_id' => fn (array $attrs) => Property::find($attrs['property_id'])->landlord_id,
        ];
    }

    public function withWaterBilling(string $type = 'consumption', ?float $flatRate = null): static
    {
        return $this->state([
            'water_billing_type' => $type,
            'water_flat_rate' => $type === 'flat_rate' ? ($flatRate ?? 500) : null,
        ]);
    }

    public function forProperty(Property $property): static
    {
        return $this->state([
            'property_id' => $property->id,
            'landlord_id' => $property->landlord_id,
        ]);
    }
}
