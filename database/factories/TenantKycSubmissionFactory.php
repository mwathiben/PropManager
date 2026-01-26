<?php

namespace Database\Factories;

use App\Enums\KycSubmissionStatus;
use App\Models\Document;
use App\Models\KycRequirement;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantKycSubmission>
 */
class TenantKycSubmissionFactory extends Factory
{
    protected $model = TenantKycSubmission::class;

    public function definition(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        $requirement = KycRequirement::factory()->forLandlord($landlord)->create();

        return [
            'user_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'requirement_id' => $requirement->id,
            'document_id' => null,
            'submission_value' => null,
            'status' => KycSubmissionStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'submitted_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => KycSubmissionStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => KycSubmissionStatus::Approved,
                'reviewed_by' => $attributes['landlord_id'],
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ];
        });
    }

    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => KycSubmissionStatus::Rejected,
                'rejection_reason' => fake()->sentence(),
                'reviewed_by' => $attributes['landlord_id'],
                'reviewed_at' => now(),
            ];
        });
    }

    public function withDocument(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'document_id' => Document::factory()->create([
                    'landlord_id' => $attributes['landlord_id'],
                    'uploaded_by' => $attributes['user_id'],
                ])->id,
            ];
        });
    }

    public function withValue(string $value): static
    {
        return $this->state(['submission_value' => $value]);
    }

    public function forTenant(User $tenant): static
    {
        return $this->state([
            'user_id' => $tenant->id,
            'landlord_id' => $tenant->landlord_id,
        ]);
    }

    public function forRequirement(KycRequirement $requirement): static
    {
        return $this->state([
            'requirement_id' => $requirement->id,
            'landlord_id' => $requirement->landlord_id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
