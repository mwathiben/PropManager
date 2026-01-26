<?php

namespace Database\Factories;

use App\Models\LandlordProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandlordProfileFactory extends Factory
{
    protected $model = LandlordProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'company_name' => fake()->optional(0.7)->company(),
            'business_registration_number' => fake()->optional(0.5)->numerify('BRN-######'),
            'profile_photo_path' => null,
            'address' => fake()->optional(0.8)->streetAddress(),
            'city' => fake()->optional(0.8)->city(),
            'country' => 'Kenya',
            'tax_id' => fake()->optional(0.4)->numerify('P##########'),
            'website' => fake()->optional(0.3)->url(),
        ];
    }

    public function complete(): static
    {
        return $this->state([
            'company_name' => fake()->company(),
            'business_registration_number' => fake()->numerify('BRN-######'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Kenya',
            'tax_id' => fake()->numerify('P##########'),
        ]);
    }

    public function incomplete(): static
    {
        return $this->state([
            'company_name' => null,
            'business_registration_number' => null,
            'address' => null,
            'city' => null,
            'tax_id' => null,
            'website' => null,
        ]);
    }

    public function withPhoto(): static
    {
        return $this->state([
            'profile_photo_path' => 'profiles/'.fake()->uuid().'.jpg',
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}
