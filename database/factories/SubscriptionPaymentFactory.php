<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPaymentFactory extends Factory
{
    protected $model = SubscriptionPayment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'amount' => fake()->randomElement([1500, 3000, 5000, 10000, 15000, 50000]),
            'currency' => 'KES',
            'status' => 'successful',
            'payment_method' => 'paystack',
            'reference' => 'PAY-'.strtoupper(fake()->unique()->bothify('????????####')),
            'paystack_reference' => strtoupper(fake()->bothify('????????????????')),
            'paystack_response' => null,
            'notes' => null,
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function successful(): static
    {
        return $this->state([
            'status' => 'successful',
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'paid_at' => null,
            'notes' => fake()->randomElement([
                'Card declined',
                'Insufficient funds',
                'Transaction timeout',
                'Bank rejected',
            ]),
        ]);
    }

    public function refunded(): static
    {
        return $this->state([
            'status' => 'refunded',
            'notes' => 'Refunded by admin',
        ]);
    }

    public function paystack(): static
    {
        return $this->state([
            'payment_method' => 'paystack',
            'paystack_reference' => strtoupper(fake()->bothify('????????????????')),
        ]);
    }

    public function manual(): static
    {
        return $this->state([
            'payment_method' => 'manual',
            'paystack_reference' => null,
            'paystack_response' => null,
            'notes' => 'Manual payment recorded by admin',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state([
            'payment_method' => 'bank_transfer',
            'paystack_reference' => null,
            'paystack_response' => null,
        ]);
    }

    public function withPaystackResponse(): static
    {
        return $this->state([
            'paystack_response' => [
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'id' => fake()->numberBetween(100000, 999999),
                    'status' => 'success',
                    'gateway_response' => 'Successful',
                    'paid_at' => now()->toISOString(),
                    'channel' => 'card',
                ],
            ],
        ]);
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
