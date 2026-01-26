<?php

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmergencyContactFactory extends Factory
{
    protected $model = EmergencyContact::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord']);

        return [
            'landlord_id' => $landlord,
            'tenant_id' => fn (array $attrs) => User::factory()->state([
                'role' => 'tenant',
                'landlord_id' => $attrs['landlord_id'],
            ]),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling', 'child', 'friend', 'colleague', 'relative']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'address' => fake()->optional(0.5)->address(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state([
            'is_primary' => true,
        ]);
    }

    public function secondary(): static
    {
        return $this->state([
            'is_primary' => false,
        ]);
    }

    public function spouse(): static
    {
        return $this->state([
            'relationship' => 'spouse',
        ]);
    }

    public function parent(): static
    {
        return $this->state([
            'relationship' => 'parent',
        ]);
    }

    public function sibling(): static
    {
        return $this->state([
            'relationship' => 'sibling',
        ]);
    }

    public function forTenant(User $tenant): static
    {
        return $this->state([
            'tenant_id' => $tenant->id,
            'landlord_id' => $tenant->landlord_id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
        ]);
    }
}
