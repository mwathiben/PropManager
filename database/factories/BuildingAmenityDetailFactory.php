<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\BuildingAmenityDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingAmenityDetailFactory extends Factory
{
    protected $model = BuildingAmenityDetail::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'amenity_key' => fake()->randomElement(['parking', 'wifi', 'water_tank', 'cctv', 'lift']),
            'quantity' => fake()->numberBetween(1, 20),
            'provider' => fake()->company(),
            'account_ref' => fake()->bothify('ACC-####'),
            'monthly_cost' => fake()->randomFloat(2, 0, 50000),
            'landlord_id' => fn (array $attrs) => Building::find($attrs['building_id'])->landlord_id,
        ];
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'landlord_id' => $building->landlord_id,
        ]);
    }
}
