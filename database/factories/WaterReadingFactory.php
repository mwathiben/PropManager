<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaterReadingFactory extends Factory
{
    protected $model = WaterReading::class;

    public function definition(): array
    {
        $unit = Unit::factory()->create();
        $previousReading = fake()->numberBetween(1000, 5000);
        $currentReading = $previousReading + fake()->numberBetween(5, 50);
        $consumption = $currentReading - $previousReading;

        return [
            'unit_id' => $unit->id,
            'landlord_id' => $unit->landlord_id,
            'reading_date' => now(),
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'consumption' => $consumption,
            'cost' => $consumption * 150,
            'status' => 'pending',
            'is_invoiced' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Invalid reading'): static
    {
        return $this->state([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
    }

    public function invoiced(): static
    {
        return $this->state([
            'status' => 'approved',
            'is_invoiced' => true,
        ]);
    }

    public function forUnit(Unit $unit, ?User $recorder = null): static
    {
        return $this->state([
            'unit_id' => $unit->id,
            'landlord_id' => $unit->landlord_id,
            'recorded_by' => $recorder?->id,
        ]);
    }

    public function forMeter(\App\Models\Meter $meter): static
    {
        return $this->state([
            'meter_id' => $meter->id,
            'unit_id' => $meter->unit_id,
            'landlord_id' => $meter->landlord_id,
        ]);
    }

    public function withConsumption(float $previousReading, float $currentReading): static
    {
        $consumption = $currentReading - $previousReading;

        return $this->state([
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'consumption' => $consumption,
            'cost' => $consumption * 150,
        ]);
    }
}
