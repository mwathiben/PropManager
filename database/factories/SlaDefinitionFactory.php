<?php

namespace Database\Factories;

use App\Models\SlaDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaDefinition>
 */
class SlaDefinitionFactory extends Factory
{
    protected $model = SlaDefinition::class;

    public function definition(): array
    {
        return [
            'landlord_id' => null,
            'category' => null,
            'subcategory' => null,
            'priority' => null,
            'response_seconds' => fake()->numberBetween(3600, 86400),
            'resolution_seconds' => fake()->numberBetween(86400, 604800),
            'is_active' => true,
        ];
    }
}
