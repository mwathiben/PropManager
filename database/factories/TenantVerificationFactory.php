<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\TenantVerification;
use App\Models\User;
use App\Models\VerificationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantVerificationFactory extends Factory
{
    protected $model = TenantVerification::class;

    public function definition(): array
    {
        return [
            'landlord_id' => fn (array $attrs) => isset($attrs['lease_id'])
                ? Lease::find($attrs['lease_id'])?->landlord_id ?? User::factory()->state(['role' => 'landlord'])
                : User::factory()->state(['role' => 'landlord']),
            'lease_id' => Lease::factory(),
            'verification_item_id' => VerificationItem::factory(),
            'status' => 'pending',
            'notes' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'notes' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $attrs['landlord_id'],
                'notes' => null,
            ];
        });
    }

    public function rejected(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'status' => 'rejected',
                'verified_at' => now(),
                'verified_by' => $attrs['landlord_id'],
                'notes' => fake()->sentence(),
            ];
        });
    }

    public function withNotes(string $notes): static
    {
        return $this->state([
            'notes' => $notes,
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
        ]);
    }

    public function forItem(VerificationItem $item): static
    {
        return $this->state([
            'verification_item_id' => $item->id,
        ]);
    }

    public function verifiedBy(User $user): static
    {
        return $this->state([
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);
    }
}
