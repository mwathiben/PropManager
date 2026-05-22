<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeterStatus;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    protected $model = Meter::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory(),
            'building_id' => null,
            'unit_id' => null,
            'parent_meter_id' => null,
            'serial_number' => $this->faker->bothify('WM-#####'),
            'utility_type' => 'water',
            'meter_type' => 'analog',
            'status' => MeterStatus::Active->value,
            'initial_reading' => 0,
            'installed_at' => now()->toDateString(),
        ];
    }

    public function replaced(): static
    {
        return $this->state(fn () => [
            'status' => MeterStatus::Replaced->value,
            'decommissioned_at' => now()->toDateString(),
        ]);
    }

    public function withBaseline(float $reading): static
    {
        return $this->state(fn () => ['initial_reading' => $reading]);
    }
}
