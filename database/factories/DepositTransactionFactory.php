<?php

namespace Database\Factories;

use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepositTransactionFactory extends Factory
{
    protected $model = DepositTransaction::class;

    public function definition(): array
    {
        $lease = Lease::factory()->create();
        $amount = fake()->numberBetween(15000, 50000);

        return [
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'processed_by' => $lease->landlord_id,
            'type' => DepositTransaction::TYPE_RECEIVED,
            'amount' => $amount,
            'balance_after' => $amount,
            'reason' => 'Initial deposit',
            'notes' => fake()->optional(0.3)->sentence(),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money']),
            'reference' => 'DEP-'.strtoupper(fake()->unique()->bothify('??######')),
            'move_out_id' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => DepositTransaction::TYPE_RECEIVED,
            'reason' => 'Deposit received',
        ]);
    }

    public function partialRefund(?float $amount = null): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => DepositTransaction::TYPE_PARTIAL_REFUND,
            'amount' => $amount ?? fake()->numberBetween(1000, (int) ($attrs['balance_after'] * 0.5)),
            'reason' => 'Partial deposit refund',
        ]);
    }

    public function fullRefund(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => DepositTransaction::TYPE_FULL_REFUND,
            'amount' => $attrs['balance_after'],
            'balance_after' => 0,
            'reason' => 'Full deposit refund - lease ended',
        ]);
    }

    public function deduction(?string $reason = null): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => DepositTransaction::TYPE_DEDUCTION,
            'amount' => fake()->numberBetween(500, min(5000, (int) $attrs['balance_after'])),
            'reason' => $reason ?? fake()->randomElement([
                'Damage repair',
                'Unpaid utilities',
                'Cleaning fee',
                'Late payment penalty',
            ]),
        ]);
    }

    public function forfeit(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => DepositTransaction::TYPE_FORFEIT,
            'amount' => $attrs['balance_after'],
            'balance_after' => 0,
            'reason' => 'Deposit forfeited due to lease violation',
        ]);
    }

    public function transfer(): static
    {
        return $this->state([
            'type' => DepositTransaction::TYPE_TRANSFER,
            'reason' => 'Deposit transferred to new lease',
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'processed_by' => $lease->landlord_id,
        ]);
    }

    public function processedBy(User $user): static
    {
        return $this->state(['processed_by' => $user->id]);
    }
}
