<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(10000, 500000);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'tenant_id' => User::factory()->state(['role' => 'tenant']),
            'status' => CheckoutSession::STATUS_OPEN,
            'total_amount_cents' => $amount,
            'currency_breakdown' => ['KES' => $amount],
            'expires_at' => now()->addHour(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state([
            'status' => CheckoutSession::STATUS_SUCCEEDED,
            'succeeded_at' => now(),
        ]);
    }
}
