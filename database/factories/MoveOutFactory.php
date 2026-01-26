<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoveOutFactory extends Factory
{
    protected $model = MoveOut::class;

    public function definition(): array
    {
        $depositHeld = fake()->numberBetween(15000, 50000);

        return [
            'landlord_id' => fn (array $attrs) => isset($attrs['lease_id'])
                ? Lease::find($attrs['lease_id'])?->landlord_id ?? User::factory()->state(['role' => 'landlord'])
                : User::factory()->state(['role' => 'landlord']),
            'lease_id' => Lease::factory(),
            'notice_date' => now(),
            'intended_move_out_date' => now()->addDays(30),
            'actual_move_out_date' => null,
            'status' => 'initiated',
            'inspection_notes' => null,
            'deposit_held' => $depositHeld,
            'total_deductions' => 0,
            'arrears_balance' => 0,
            'refund_amount' => $depositHeld,
            'settlement_method' => null,
            'settlement_reference' => null,
            'settled_at' => null,
            'processed_by' => null,
        ];
    }

    public function initiated(): static
    {
        return $this->state([
            'status' => 'initiated',
        ]);
    }

    public function inspectionPending(): static
    {
        return $this->state([
            'status' => 'inspection_pending',
        ]);
    }

    public function inspectionComplete(): static
    {
        return $this->state([
            'status' => 'inspection_complete',
            'inspection_notes' => fake()->paragraph(),
        ]);
    }

    public function settlementPending(): static
    {
        return $this->state([
            'status' => 'settlement_pending',
            'inspection_notes' => fake()->paragraph(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attrs) {
            return [
                'status' => 'completed',
                'actual_move_out_date' => now(),
                'settled_at' => now(),
                'settlement_method' => fake()->randomElement(['bank_transfer', 'mpesa', 'cash', 'cheque']),
                'settlement_reference' => fake()->uuid(),
                'inspection_notes' => fake()->paragraph(),
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
        ]);
    }

    public function withDeductions(float $amount): static
    {
        return $this->state(function (array $attrs) use ($amount) {
            $depositHeld = $attrs['deposit_held'] ?? 30000;
            $arrears = $attrs['arrears_balance'] ?? 0;

            return [
                'total_deductions' => $amount,
                'refund_amount' => max(0, $depositHeld - $amount - $arrears),
            ];
        });
    }

    public function withArrears(float $amount): static
    {
        return $this->state(function (array $attrs) use ($amount) {
            $depositHeld = $attrs['deposit_held'] ?? 30000;
            $deductions = $attrs['total_deductions'] ?? 0;

            return [
                'arrears_balance' => $amount,
                'refund_amount' => max(0, $depositHeld - $deductions - $amount),
            ];
        });
    }

    public function noRefund(): static
    {
        return $this->state(function (array $attrs) {
            $depositHeld = $attrs['deposit_held'] ?? 30000;

            return [
                'total_deductions' => $depositHeld,
                'refund_amount' => 0,
            ];
        });
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'deposit_held' => $lease->deposit_amount ?? 30000,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
        ]);
    }

    public function processedBy(User $user): static
    {
        return $this->state([
            'processed_by' => $user->id,
        ]);
    }
}
