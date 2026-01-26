<?php

namespace Database\Factories;

use App\Models\TenantActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantActivityFactory extends Factory
{
    protected $model = TenantActivity::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord']);
        $type = fake()->randomElement([
            TenantActivity::TYPE_LEASE_CREATED,
            TenantActivity::TYPE_PAYMENT_RECEIVED,
            TenantActivity::TYPE_INVOICE_GENERATED,
            TenantActivity::TYPE_NOTE_ADDED,
            TenantActivity::TYPE_PROFILE_UPDATED,
        ]);

        return [
            'landlord_id' => $landlord,
            'tenant_id' => fn (array $attrs) => User::factory()->state([
                'role' => 'tenant',
                'landlord_id' => $attrs['landlord_id'],
            ]),
            'type' => $type,
            'description' => $this->getDescriptionForType($type),
            'metadata' => [],
            'performed_by' => fn (array $attrs) => $attrs['landlord_id'],
        ];
    }

    private function getDescriptionForType(string $type): string
    {
        return match ($type) {
            TenantActivity::TYPE_LEASE_CREATED => 'Lease agreement created',
            TenantActivity::TYPE_LEASE_RENEWED => 'Lease agreement renewed',
            TenantActivity::TYPE_LEASE_TERMINATED => 'Lease agreement terminated',
            TenantActivity::TYPE_RENT_ADJUSTED => 'Rent amount adjusted',
            TenantActivity::TYPE_PAYMENT_RECEIVED => 'Payment received',
            TenantActivity::TYPE_INVOICE_GENERATED => 'Invoice generated',
            TenantActivity::TYPE_DOCUMENT_UPLOADED => 'Document uploaded',
            TenantActivity::TYPE_VERIFICATION_SUBMITTED => 'Verification documents submitted',
            TenantActivity::TYPE_VERIFICATION_APPROVED => 'Verification approved',
            TenantActivity::TYPE_VERIFICATION_REJECTED => 'Verification rejected',
            TenantActivity::TYPE_MOVE_OUT_INITIATED => 'Move-out process initiated',
            TenantActivity::TYPE_MOVE_OUT_COMPLETED => 'Move-out completed',
            TenantActivity::TYPE_NOTE_ADDED => 'Note added to tenant profile',
            TenantActivity::TYPE_PROFILE_UPDATED => 'Tenant profile updated',
            TenantActivity::TYPE_EMERGENCY_CONTACT_ADDED => 'Emergency contact added',
            default => 'Activity recorded',
        };
    }

    public function leaseCreated(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_LEASE_CREATED,
            'description' => 'Lease agreement created',
        ]);
    }

    public function leaseRenewed(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_LEASE_RENEWED,
            'description' => 'Lease agreement renewed',
        ]);
    }

    public function leaseTerminated(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_LEASE_TERMINATED,
            'description' => 'Lease agreement terminated',
        ]);
    }

    public function rentAdjusted(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_RENT_ADJUSTED,
            'description' => 'Rent amount adjusted',
            'metadata' => [
                'old_amount' => fake()->numberBetween(10000, 30000),
                'new_amount' => fake()->numberBetween(15000, 40000),
            ],
        ]);
    }

    public function paymentReceived(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_PAYMENT_RECEIVED,
            'description' => 'Payment received',
            'metadata' => [
                'amount' => fake()->numberBetween(10000, 50000),
                'method' => fake()->randomElement(['mpesa', 'bank_transfer', 'cash']),
            ],
        ]);
    }

    public function invoiceGenerated(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_INVOICE_GENERATED,
            'description' => 'Invoice generated',
        ]);
    }

    public function documentUploaded(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_DOCUMENT_UPLOADED,
            'description' => 'Document uploaded',
        ]);
    }

    public function verificationSubmitted(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_VERIFICATION_SUBMITTED,
            'description' => 'Verification documents submitted',
        ]);
    }

    public function verificationApproved(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_VERIFICATION_APPROVED,
            'description' => 'Verification approved',
        ]);
    }

    public function verificationRejected(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_VERIFICATION_REJECTED,
            'description' => 'Verification rejected',
        ]);
    }

    public function moveOutInitiated(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_MOVE_OUT_INITIATED,
            'description' => 'Move-out process initiated',
        ]);
    }

    public function moveOutCompleted(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_MOVE_OUT_COMPLETED,
            'description' => 'Move-out completed',
        ]);
    }

    public function noteAdded(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_NOTE_ADDED,
            'description' => 'Note added to tenant profile',
        ]);
    }

    public function profileUpdated(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_PROFILE_UPDATED,
            'description' => 'Tenant profile updated',
        ]);
    }

    public function emergencyContactAdded(): static
    {
        return $this->state([
            'type' => TenantActivity::TYPE_EMERGENCY_CONTACT_ADDED,
            'description' => 'Emergency contact added',
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state([
            'metadata' => $metadata,
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

    public function performedBy(User $user): static
    {
        return $this->state([
            'performed_by' => $user->id,
        ]);
    }
}
