<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lease;
use App\Models\RentEscalation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentEscalation>
 */
class RentEscalationFactory extends Factory
{
    protected $model = RentEscalation::class;

    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'landlord_id' => User::factory(),
            'escalation_type' => RentEscalation::TYPE_PERCENTAGE,
            'amount' => 10.00,
            'effective_date' => now()->addMonth()->toDateString(),
            'status' => RentEscalation::STATUS_SCHEDULED,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['effective_date' => now()->subDay()->toDateString()]);
    }

    public function fixed(float $amount = 5000): static
    {
        return $this->state(fn () => [
            'escalation_type' => RentEscalation::TYPE_FIXED_AMOUNT,
            'amount' => $amount,
        ]);
    }
}
