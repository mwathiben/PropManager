<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'unit_number' => strtoupper(fake()->randomLetter()).fake()->numberBetween(101, 999),
            'floor_number' => fake()->numberBetween(1, 5),
            'status' => 'vacant',
            'target_rent' => fake()->numberBetween(15000, 50000),
            'meter_number' => fake()->optional(0.7)->numerify('MTR-######'),
            'landlord_id' => fn (array $attrs) => Building::find($attrs['building_id'])->landlord_id,
        ];
    }

    public function vacant(): static
    {
        return $this->state(['status' => 'vacant']);
    }

    public function occupied(): static
    {
        return $this->state(['status' => 'occupied']);
    }

    public function maintenance(): static
    {
        return $this->state(['status' => 'maintenance']);
    }

    public function arrears(): static
    {
        return $this->state(['status' => 'arrears']);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'landlord_id' => $building->landlord_id,
        ]);
    }
}
