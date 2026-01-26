<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\TenantPaymentVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantPaymentVerificationFactory extends Factory
{
    protected $model = TenantPaymentVerification::class;

    public function definition(): array
    {
        $depositRequired = fake()->numberBetween(15000, 50000);
        $firstRentRequired = fake()->numberBetween(15000, 50000);
        $otherCharges = fake()->optional(0.3)->numberBetween(500, 5000) ?? 0;
        $totalRequired = $depositRequired + $firstRentRequired + $otherCharges;

        return [
            'lease_id' => Lease::factory(),
            'landlord_id' => fn (array $attrs) => isset($attrs['lease_id'])
                ? Lease::find($attrs['lease_id'])?->landlord_id ?? User::factory()->state(['role' => 'landlord'])
                : User::factory()->state(['role' => 'landlord']),
            'status' => TenantPaymentVerification::STATUS_PENDING_PAYMENT,
            'deposit_required' => $depositRequired,
            'first_rent_required' => $firstRentRequired,
            'other_charges' => $otherCharges,
            'other_charges_description' => $otherCharges > 0 ? 'Service charge / Admin fee' : null,
            'total_required' => $totalRequired,
            'amount_paid' => 0,
            'rejection_reason' => null,
            'submitted_at' => null,
            'verified_at' => null,
            'verified_by' => null,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state([
            'status' => TenantPaymentVerification::STATUS_PENDING_PAYMENT,
            'amount_paid' => 0,
            'submitted_at' => null,
            'verified_at' => null,
        ]);
    }

    public function paymentSubmitted(): static
    {
        return $this->state(function (array $attrs) {
            $totalRequired = $attrs['total_required']
                ?? ($attrs['deposit_required'] ?? 30000) + ($attrs['first_rent_required'] ?? 25000) + ($attrs['other_charges'] ?? 0);

            return [
                'status' => TenantPaymentVerification::STATUS_PAYMENT_SUBMITTED,
                'submitted_at' => now(),
                'amount_paid' => $totalRequired,
            ];
        });
    }

    public function paymentVerified(): static
    {
        return $this->state(function (array $attrs) {
            $totalRequired = $attrs['total_required']
                ?? ($attrs['deposit_required'] ?? 30000) + ($attrs['first_rent_required'] ?? 25000) + ($attrs['other_charges'] ?? 0);

            return [
                'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $attrs['landlord_id'],
                'amount_paid' => $totalRequired,
                'submitted_at' => now()->subHour(),
            ];
        });
    }

    public function rejected(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'status' => TenantPaymentVerification::STATUS_REJECTED,
                'rejection_reason' => fake()->randomElement([
                    'Payment proof unclear',
                    'Amount does not match required',
                    'Invalid payment reference',
                    'Duplicate submission',
                ]),
                'verified_by' => $attrs['landlord_id'],
                'submitted_at' => now()->subHour(),
            ];
        });
    }

    public function partiallyPaid(float $amount): static
    {
        return $this->state([
            'amount_paid' => $amount,
        ]);
    }

    public function fullyPaid(): static
    {
        return $this->state(function (array $attrs) {
            $totalRequired = $attrs['total_required']
                ?? ($attrs['deposit_required'] ?? 30000) + ($attrs['first_rent_required'] ?? 25000) + ($attrs['other_charges'] ?? 0);

            return [
                'amount_paid' => $totalRequired,
            ];
        });
    }

    public function withOtherCharges(float $amount, string $description): static
    {
        return $this->state(function (array $attrs) use ($amount, $description) {
            $depositRequired = $attrs['deposit_required'] ?? 30000;
            $firstRentRequired = $attrs['first_rent_required'] ?? 25000;

            return [
                'other_charges' => $amount,
                'other_charges_description' => $description,
                'total_required' => $depositRequired + $firstRentRequired + $amount,
            ];
        });
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'deposit_required' => $lease->deposit_amount ?? 30000,
            'first_rent_required' => $lease->rent_amount ?? 25000,
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
