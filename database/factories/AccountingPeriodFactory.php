<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status' => AccountingPeriod::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
