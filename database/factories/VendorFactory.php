<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'tax_id' => fake()->optional(0.5)->numerify('P###########'),
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
