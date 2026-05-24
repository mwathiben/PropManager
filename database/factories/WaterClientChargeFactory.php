<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WaterClientCharge;
use App\Models\WaterConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterClientCharge>
 */
class WaterClientChargeFactory extends Factory
{
    protected $model = WaterClientCharge::class;

    public function definition(): array
    {
        $waterDue = $this->faker->randomFloat(2, 100, 5000);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'water_connection_id' => WaterConnection::factory(),
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'consumption' => $this->faker->randomFloat(2, 1, 50),
            'water_due' => $waterDue,
            'amount_paid' => 0,
            'status' => 'due',
            'due_date' => now()->addDays(14)->toDateString(),
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'amount_paid' => $attrs['water_due'],
            'status' => 'paid',
        ]);
    }

    public function flatRate(): static
    {
        return $this->state(['consumption' => null]);
    }
}
