<?php

namespace Database\Factories;

use App\Models\PlatformBillingSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformBillingSettingFactory extends Factory
{
    protected $model = PlatformBillingSetting::class;

    public function definition(): array
    {
        return [
            'active_billing_model' => 'transaction_fee',
            'transaction_fee_percentage' => 2.50,
            'minimum_fee' => 50.00,
            'maximum_fee' => null,
            'fee_bearer' => 'landlord',
            'hybrid_subscription_discount' => 100.00,
            'is_active' => true,
            'updated_by' => User::factory()->state(['role' => 'super_admin']),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function transactionFee(): static
    {
        return $this->state([
            'active_billing_model' => 'transaction_fee',
            'transaction_fee_percentage' => 2.50,
        ]);
    }

    public function subscription(): static
    {
        return $this->state([
            'active_billing_model' => 'subscription',
            'transaction_fee_percentage' => 0,
        ]);
    }

    public function hybrid(): static
    {
        return $this->state([
            'active_billing_model' => 'hybrid',
            'transaction_fee_percentage' => 1.50,
            'hybrid_subscription_discount' => 50.00,
        ]);
    }

    public function landlordPays(): static
    {
        return $this->state(['fee_bearer' => 'landlord']);
    }

    public function platformPays(): static
    {
        return $this->state(['fee_bearer' => 'platform']);
    }

    public function sharedFees(): static
    {
        return $this->state(['fee_bearer' => 'shared']);
    }

    public function withFeePercentage(float $percentage): static
    {
        return $this->state(['transaction_fee_percentage' => $percentage]);
    }

    public function withMinimumFee(float $minimum): static
    {
        return $this->state(['minimum_fee' => $minimum]);
    }

    public function withMaximumFee(float $maximum): static
    {
        return $this->state(['maximum_fee' => $maximum]);
    }

    public function withFeeLimits(float $min, float $max): static
    {
        return $this->state([
            'minimum_fee' => $min,
            'maximum_fee' => $max,
        ]);
    }

    public function updatedBy(User $user): static
    {
        return $this->state(['updated_by' => $user->id]);
    }
}
