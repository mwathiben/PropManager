<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycRequirement>
 */
class KycRequirementFactory extends Factory
{
    protected $model = KycRequirement::class;

    public function definition(): array
    {
        $types = ['selfie', 'national_id', 'signed_lease', 'proof_of_income', 'reference_letter'];
        $type = fake()->randomElement($types);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'building_id' => null,
            'requirement_type' => $type,
            'label' => match ($type) {
                'selfie' => 'Profile Photo / Selfie',
                'national_id' => 'National ID',
                'signed_lease' => 'Signed Lease Agreement',
                'proof_of_income' => 'Proof of Income',
                'reference_letter' => 'Reference Letter',
                default => ucwords(str_replace('_', ' ', $type)),
            },
            'description' => fake()->sentence(),
            'is_required' => true,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false]);
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'landlord_id' => $building->landlord_id,
        ]);
    }

    public function platformDefault(): static
    {
        return $this->state([
            'landlord_id' => null,
            'building_id' => null,
        ]);
    }

    public function selfie(): static
    {
        return $this->state([
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo / Selfie',
            'description' => 'A clear photo of your face for identification purposes.',
        ]);
    }

    public function nationalId(): static
    {
        return $this->state([
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'description' => 'Upload a clear photo of both sides of your National ID or Passport.',
        ]);
    }

    public function signedLease(): static
    {
        return $this->state([
            'requirement_type' => 'signed_lease',
            'label' => 'Signed Lease Agreement',
            'description' => 'Upload the signed lease agreement document provided by your landlord.',
        ]);
    }
}
