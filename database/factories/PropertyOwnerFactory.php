<?php

namespace Database\Factories;

use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyOwner>
 */
class PropertyOwnerFactory extends Factory
{
    protected $model = PropertyOwner::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.7)->phoneNumber(),
            'id_number' => fake()->optional(0.8)->numerify('###########'),
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
