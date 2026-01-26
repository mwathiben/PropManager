<?php

namespace Database\Factories;

use App\Models\LandlordPayoutAccount;
use App\Models\Payment;
use App\Models\PlatformFee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformFeeFactory extends Factory
{
    protected $model = PlatformFee::class;

    public function definition(): array
    {
        $grossAmount = fake()->randomFloat(2, 1000, 50000);
        $feePercentage = fake()->randomFloat(2, 1.5, 5);
        $feeAmount = round($grossAmount * ($feePercentage / 100), 2);
        $netAmount = $grossAmount - $feeAmount;

        return [
            'payment_id' => Payment::factory(),
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'payout_account_id' => null,
            'gross_amount' => $grossAmount,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'fee_type' => fake()->randomElement(array_keys(PlatformFee::FEE_TYPES)),
            'fee_percentage_applied' => $feePercentage,
            'status' => fake()->randomElement(array_keys(PlatformFee::STATUSES)),
            'paystack_split_reference' => fake()->optional()->uuid(),
            'split_details' => null,
            'collected_at' => null,
            'settled_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'collected_at' => null,
            'settled_at' => null,
        ]);
    }

    public function collected(): static
    {
        return $this->state([
            'status' => 'collected',
            'collected_at' => now(),
            'settled_at' => null,
        ]);
    }

    public function settled(): static
    {
        return $this->state([
            'status' => 'settled',
            'collected_at' => now()->subDays(2),
            'settled_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'notes' => 'Payment processing failed',
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'status' => 'refunded',
            'notes' => 'Refund processed',
        ]);
    }

    public function transactionPercentage(): static
    {
        return $this->state(['fee_type' => 'transaction_percentage']);
    }

    public function subscriptionFlat(): static
    {
        return $this->state(fn () => [
            'fee_type' => 'subscription_flat',
            'fee_amount' => 0,
            'fee_percentage_applied' => 0,
            'net_amount' => fn (array $attrs) => $attrs['gross_amount'],
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(['fee_type' => 'hybrid']);
    }

    public function withSplitDetails(): static
    {
        return $this->state([
            'paystack_split_reference' => fake()->uuid(),
            'split_details' => [
                'split_code' => 'SPL_'.fake()->regexify('[a-zA-Z0-9]{10}'),
                'subaccounts' => [
                    [
                        'id' => fake()->uuid(),
                        'share' => 9500,
                        'type' => 'percentage',
                    ],
                    [
                        'id' => 'platform',
                        'share' => 500,
                        'type' => 'percentage',
                    ],
                ],
            ],
        ]);
    }

    public function withAmount(float $grossAmount, float $feePercentage = 2.5): static
    {
        $feeAmount = round($grossAmount * ($feePercentage / 100), 2);

        return $this->state([
            'gross_amount' => $grossAmount,
            'fee_amount' => $feeAmount,
            'net_amount' => $grossAmount - $feeAmount,
            'fee_percentage_applied' => $feePercentage,
        ]);
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(['payment_id' => $payment->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function forPayoutAccount(LandlordPayoutAccount $account): static
    {
        return $this->state(['payout_account_id' => $account->id]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function historical(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subDays(fake()->numberBetween(30, 90)),
        ]);
    }
}
