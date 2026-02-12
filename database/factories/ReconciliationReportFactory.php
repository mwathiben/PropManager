<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReconciliationReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReconciliationReportFactory extends Factory
{
    protected $model = ReconciliationReport::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'provider' => 'paystack',
            'status' => 'completed',
            'period_from' => now()->subDay()->startOfDay(),
            'period_to' => now()->subDay()->endOfDay(),
            'local_count' => fake()->numberBetween(5, 50),
            'remote_count' => fake()->numberBetween(5, 50),
            'matched_count' => fake()->numberBetween(3, 30),
            'discrepancy_count' => 0,
            'result_data' => [],
            'error_message' => null,
            'alert_sent' => false,
            'reconciled_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'API connection timeout',
            'discrepancy_count' => 0,
            'result_data' => null,
        ]);
    }

    public function withDiscrepancies(int $count = 3): static
    {
        $discrepancies = [];
        for ($i = 0; $i < $count; $i++) {
            $discrepancies[] = [
                'type' => 'missing_locally',
                'reference' => 'REF_'.strtoupper(fake()->bothify('????####')),
                'local_amount' => null,
                'remote_amount' => fake()->randomFloat(2, 1000, 50000),
                'currency' => 'KES',
                'remote_status' => 'success',
            ];
        }

        return $this->state([
            'status' => 'completed',
            'discrepancy_count' => $count,
            'result_data' => $discrepancies,
            'error_message' => null,
        ]);
    }
}
