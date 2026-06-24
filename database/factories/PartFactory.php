<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => fake()->words(2, true),
            'sku' => fake()->optional()->bothify('SKU-####'),
            'category' => fake()->optional()->word(),
            'cost_per_unit_cents' => fake()->numberBetween(500, 50000),
            'qty_available' => fake()->numberBetween(0, 100),
            'reorder_threshold' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
