<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentDispute>
 */
class PaymentDisputeFactory extends Factory
{
    protected $model = PaymentDispute::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'landlord_id' => fn (array $attrs) => Payment::find($attrs['payment_id'])?->landlord_id
                ?? User::factory()->state(['role' => 'landlord']),
            'gateway' => fake()->randomElement(['stripe', 'paystack']),
            'gateway_dispute_id' => 'dp_'.fake()->unique()->bothify('??########'),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'KES',
            'reason' => fake()->optional()->sentence(),
            'status' => PaymentDispute::STATUS_OPEN,
            'opened_at' => now(),
        ];
    }

    public function won(): static
    {
        return $this->state([
            'status' => PaymentDispute::STATUS_WON,
            'resolved_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state([
            'status' => PaymentDispute::STATUS_LOST,
            'resolved_at' => now(),
        ]);
    }
}
