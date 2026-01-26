<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WaterSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaterSettingFactory extends Factory
{
    protected $model = WaterSetting::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'rate_per_unit' => fake()->randomFloat(2, 100, 300),
            'billing_day' => fake()->numberBetween(1, 28),
            'is_enabled' => true,
        ];
    }

    public function enabled(): static
    {
        return $this->state(['is_enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function withRate(float $rate): static
    {
        return $this->state(['rate_per_unit' => $rate]);
    }

    public function withBillingDay(int $day): static
    {
        return $this->state(['billing_day' => $day]);
    }
}
