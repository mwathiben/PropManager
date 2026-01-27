<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        $unit = Unit::factory()->create();

        return [
            'unit_id' => $unit->id,
            'tenant_id' => User::factory()->state([
                'role' => 'tenant',
                'landlord_id' => $unit->landlord_id,
            ]),
            'landlord_id' => $unit->landlord_id,
            'rent_amount' => $unit->target_rent ?? fake()->numberBetween(15000, 50000),
            'deposit_amount' => $unit->target_rent ?? fake()->numberBetween(15000, 50000),
            'service_charge' => fake()->boolean(30) ? fake()->numberBetween(500, 2000) : 0,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function terminated(): static
    {
        return $this->state([
            'is_active' => false,
            'end_date' => now(),
        ]);
    }

    public function withWalletBalance(float $amount): static
    {
        return $this->state(['wallet_balance' => $amount]);
    }

    public function forUnit(Unit $unit, ?User $tenant = null): static
    {
        $tenantUser = $tenant ?? User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $unit->landlord_id,
        ]);

        return $this->state([
            'unit_id' => $unit->id,
            'tenant_id' => $tenantUser->id,
            'landlord_id' => $unit->landlord_id,
            'rent_amount' => $unit->target_rent,
            'deposit_amount' => $unit->target_rent,
        ]);
    }
}
