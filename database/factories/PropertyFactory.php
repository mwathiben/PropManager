<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Property',
            'address' => fake()->address(),
            'type' => fake()->randomElement(['apartment', 'commercial', 'mixed']),
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
        ];
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
