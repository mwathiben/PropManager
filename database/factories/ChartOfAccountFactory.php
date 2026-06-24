<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'account_code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'account_name' => fake()->words(2, true),
            'account_type' => fake()->randomElement(ChartOfAccount::TYPES),
            'is_active' => true,
        ];
    }
}
