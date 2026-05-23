<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WaterConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterConnection>
 */
class WaterConnectionFactory extends Factory
{
    protected $model = WaterConnection::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'user_id' => null,
            'unit_id' => null,
            'meter_id' => null,
            'identifier' => 'LINE-'.$this->faker->unique()->numberBetween(100, 9999),
            'client_name' => $this->faker->name(),
            'billing_mode' => 'metered',
            'client_rate' => $this->faker->randomFloat(2, 50, 300),
            'status' => 'active',
            'connected_at' => now()->toDateString(),
            'notes' => null,
        ];
    }
}
