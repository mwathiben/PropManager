<?php

namespace Database\Factories;

use App\Models\OwnerPayout;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerPayout>
 */
class OwnerPayoutFactory extends Factory
{
    protected $model = OwnerPayout::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'property_owner_id' => PropertyOwner::factory(),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'KES',
            'paid_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'method' => fake()->randomElement(['bank_transfer', 'mpesa', 'cheque', 'cash', 'other']),
            'reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional(0.3)->sentence(),
            'voided_at' => null,
        ];
    }

    public function voided(): static
    {
        return $this->state(['voided_at' => now()]);
    }

    public function forOwner(PropertyOwner $owner): static
    {
        return $this->state(['landlord_id' => $owner->landlord_id, 'property_owner_id' => $owner->id]);
    }
}
