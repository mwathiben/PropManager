<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\RentHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RentHistoryFactory extends Factory
{
    protected $model = RentHistory::class;

    public function definition(): array
    {
        $oldAmount = fake()->numberBetween(15000, 45000);
        $changePercent = fake()->randomElement([5, 10, 15, 20]);
        $newAmount = $oldAmount * (1 + $changePercent / 100);

        return [
            'lease_id' => Lease::factory(),
            'old_amount' => $oldAmount,
            'new_amount' => round($newAmount, 2),
            'effective_date' => fake()->dateTimeBetween('now', '+3 months'),
            'reason' => fake()->optional(0.7)->randomElement([
                'Annual rent review',
                'Market rate adjustment',
                'Lease renewal',
                'Inflation adjustment',
            ]),
            'notification_sent' => false,
        ];
    }

    public function notified(): static
    {
        return $this->state(['notification_sent' => true]);
    }

    public function decrease(): static
    {
        return $this->state(fn (array $attrs) => [
            'new_amount' => round($attrs['old_amount'] * 0.9, 2),
            'reason' => 'Rent reduction',
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'old_amount' => $lease->rent_amount,
        ]);
    }
}
