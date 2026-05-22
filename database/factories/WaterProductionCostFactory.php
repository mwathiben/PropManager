<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WaterProductionCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterProductionCost>
 */
class WaterProductionCostFactory extends Factory
{
    protected $model = WaterProductionCost::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory(),
            'building_id' => null,
            'cost_date' => now()->toDateString(),
            'amount' => $this->faker->randomFloat(2, 1000, 20000),
            'category' => $this->faker->randomElement(WaterProductionCost::CATEGORIES),
            'note' => null,
        ];
    }
}
