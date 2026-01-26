<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'email' => fake()->unique()->safeEmail(),
            'target_user_id' => null,
            'token' => Invitation::generateToken(),
            'property_id' => Property::factory(),
            'accepted_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'accepted_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(function () {
            return [
                'accepted_at' => null,
                'created_at' => now()->subDays(31),
                'updated_at' => now()->subDays(31),
            ];
        });
    }

    public function forExistingUser(User $user): static
    {
        return $this->state([
            'target_user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(function () use ($landlord) {
            return [
                'landlord_id' => $landlord->id,
                'property_id' => Property::factory()->create(['landlord_id' => $landlord->id])->id,
            ];
        });
    }

    public function forProperty(Property $property): static
    {
        return $this->state([
            'property_id' => $property->id,
            'landlord_id' => $property->landlord_id,
        ]);
    }
}
