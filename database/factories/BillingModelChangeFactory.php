<?php

namespace Database\Factories;

use App\Models\BillingModelChange;
use App\Models\PlatformBillingSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingModelChangeFactory extends Factory
{
    protected $model = BillingModelChange::class;

    public function definition(): array
    {
        $billingModels = array_keys(PlatformBillingSetting::BILLING_MODELS);

        return [
            'from_model' => fake()->randomElement($billingModels),
            'to_model' => fake()->randomElement($billingModels),
            'changed_by' => User::factory()->state(['role' => 'super_admin']),
            'effective_date' => now(),
            'reason' => fake()->sentence(),
            'settings_snapshot' => [
                'transaction_fee_percentage' => 2.50,
                'minimum_fee' => 50.00,
                'maximum_fee' => null,
                'fee_bearer' => 'landlord',
            ],
        ];
    }

    public function initialSetup(): static
    {
        return $this->state([
            'from_model' => null,
            'to_model' => 'transaction_fee',
            'reason' => 'Initial platform setup',
        ]);
    }

    public function toTransactionFee(): static
    {
        return $this->state([
            'to_model' => 'transaction_fee',
            'reason' => 'Switched to transaction fee model',
        ]);
    }

    public function toSubscription(): static
    {
        return $this->state([
            'to_model' => 'subscription',
            'reason' => 'Switched to subscription model',
        ]);
    }

    public function toHybrid(): static
    {
        return $this->state([
            'to_model' => 'hybrid',
            'reason' => 'Switched to hybrid model',
        ]);
    }

    public function feePercentageChange(): static
    {
        $model = fake()->randomElement(array_keys(PlatformBillingSetting::BILLING_MODELS));

        return $this->state([
            'from_model' => $model,
            'to_model' => $model,
            'reason' => 'Updated fee percentage',
        ]);
    }

    public function changedBy(User $user): static
    {
        return $this->state(['changed_by' => $user->id]);
    }

    public function effectiveOn(\DateTimeInterface $date): static
    {
        return $this->state(['effective_date' => $date]);
    }

    public function withSnapshot(array $snapshot): static
    {
        return $this->state(['settings_snapshot' => $snapshot]);
    }

    public function recent(): static
    {
        return $this->state([
            'effective_date' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    public function historical(): static
    {
        return $this->state([
            'effective_date' => now()->subMonths(fake()->numberBetween(1, 12)),
        ]);
    }
}
