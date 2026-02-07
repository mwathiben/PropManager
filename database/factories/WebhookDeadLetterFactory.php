<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookDeadLetter;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookDeadLetterFactory extends Factory
{
    protected $model = WebhookDeadLetter::class;

    public function definition(): array
    {
        return [
            'landlord_id' => null,
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'event_type' => fake()->randomElement([
                'stk_callback',
                'c2b_confirmation',
                'charge.success',
                'payment.complete',
                'b2c_result',
            ]),
            'payload' => fn () => [
                'transaction_id' => strtoupper(fake()->bothify('TXN########')),
                'amount' => fake()->numberBetween(1000, 100000),
                'currency' => 'KES',
                'timestamp' => now()->toISOString(),
            ],
            'headers' => null,
            'error_reason' => fake()->randomElement([
                'Invoice not found',
                'Amount mismatch: expected 5000.00, received 4500.00',
                'Challenge validation failed',
                'Payment processing exception',
                'Invalid payload structure',
                'Tenant not matched',
            ]),
            'error_class' => WebhookDeadLetter::ERROR_TRANSIENT,
            'attempts' => 1,
            'max_retries' => 5,
            'next_retry_at' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ];
    }

    public function unresolved(): static
    {
        return $this->state([
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
            'resolution_notes' => fake()->randomElement([
                'Payment found in bank statement',
                'Manually reconciled',
                'Duplicate webhook - original processed',
                'Provider confirmed payment failed',
            ]),
        ]);
    }

    public function mpesa(): static
    {
        return $this->state([
            'provider' => WebhookDeadLetter::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
        ]);
    }

    public function paystack(): static
    {
        return $this->state([
            'provider' => WebhookDeadLetter::PROVIDER_PAYSTACK,
            'event_type' => 'charge.success',
        ]);
    }

    public function intasend(): static
    {
        return $this->state([
            'provider' => WebhookDeadLetter::PROVIDER_INTASEND,
            'event_type' => 'payment.complete',
        ]);
    }

    public function bank(): static
    {
        return $this->state([
            'provider' => WebhookDeadLetter::PROVIDER_BANK,
            'event_type' => 'payment.received',
        ]);
    }

    public function transient(): static
    {
        return $this->state([
            'error_class' => WebhookDeadLetter::ERROR_TRANSIENT,
        ]);
    }

    public function permanent(): static
    {
        return $this->state([
            'error_class' => WebhookDeadLetter::ERROR_PERMANENT,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
        ]);
    }

    public function withPayload(array $payload): static
    {
        return $this->state(['payload' => $payload]);
    }

    public function withHeaders(array $headers): static
    {
        return $this->state(['headers' => $headers]);
    }

    public function withAttempts(int $count): static
    {
        return $this->state(['attempts' => $count]);
    }
}
