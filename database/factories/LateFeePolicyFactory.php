<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\LateFeePolicy;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LateFeePolicyFactory extends Factory
{
    protected $model = LateFeePolicy::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'property_id' => null,
            'building_id' => null,
            'name' => fake()->randomElement(['Standard Late Fee', 'Premium Late Fee', 'Flexible Late Fee']),
            'grace_period_days' => fake()->numberBetween(3, 7),
            'fee_type' => 'percentage',
            'fee_percentage' => fake()->randomElement([5.0, 10.0, 15.0]),
            'fee_amount' => null,
            'is_compounding' => false,
            'compounding_frequency' => null,
            'max_fee_cap' => fake()->optional(0.5)->numberBetween(5000, 20000),
            'is_active' => true,
            'priority' => 0,
        ];
    }

    public function percentage(float $rate = 10.0): static
    {
        return $this->state([
            'fee_type' => 'percentage',
            'fee_percentage' => $rate,
            'fee_amount' => null,
        ]);
    }

    public function fixed(float $amount = 1000.0): static
    {
        return $this->state([
            'fee_type' => 'fixed',
            'fee_percentage' => null,
            'fee_amount' => $amount,
        ]);
    }

    public function compounding(string $frequency = 'monthly'): static
    {
        return $this->state([
            'is_compounding' => true,
            'compounding_frequency' => $frequency,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forProperty(Property $property): static
    {
        return $this->state([
            'property_id' => $property->id,
            'landlord_id' => $property->landlord_id,
            'building_id' => null,
        ]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'property_id' => $building->property_id,
            'landlord_id' => $building->landlord_id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
