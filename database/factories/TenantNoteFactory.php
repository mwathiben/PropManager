<?php

namespace Database\Factories;

use App\Models\TenantNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantNoteFactory extends Factory
{
    protected $model = TenantNote::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord']);

        return [
            'landlord_id' => $landlord,
            'tenant_id' => fn (array $attrs) => User::factory()->state([
                'role' => 'tenant',
                'landlord_id' => $attrs['landlord_id'],
            ]),
            'content' => fake()->paragraph(),
            'created_by' => fn (array $attrs) => $attrs['landlord_id'],
            'is_pinned' => false,
        ];
    }

    public function pinned(): static
    {
        return $this->state([
            'is_pinned' => true,
        ]);
    }

    public function unpinned(): static
    {
        return $this->state([
            'is_pinned' => false,
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
            'created_by' => $landlord->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state([
            'created_by' => $user->id,
        ]);
    }
}
