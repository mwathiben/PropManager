<?php

namespace Database\Factories;

use App\Models\PlatformFeeTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformFeeTierFactory extends Factory
{
    protected $model = PlatformFeeTier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'min_volume' => 0,
            'max_volume' => 50000,
            'fee_percentage' => 3.00,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withRange(float $min, ?float $max): static
    {
        return $this->state([
            'min_volume' => $min,
            'max_volume' => $max,
        ]);
    }

    public function withPercentage(float $pct): static
    {
        return $this->state(['fee_percentage' => $pct]);
    }
}
