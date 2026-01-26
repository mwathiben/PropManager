<?php

namespace Database\Factories;

use App\Models\BankWebhookLog;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankWebhookLogFactory extends Factory
{
    protected $model = BankWebhookLog::class;

    public function definition(): array
    {
        return [
            'bank_code' => fake()->randomElement(['EQUITY', 'KCB', 'COOP', 'ABSA', 'STANBIC']),
            'event_type' => fake()->randomElement(['payment.received', 'payment.confirmed', 'payment.failed', 'account.credited']),
            'payload' => [
                'transaction_id' => strtoupper(fake()->bothify('TXN########')),
                'amount' => fake()->numberBetween(1000, 100000),
                'currency' => 'KES',
                'timestamp' => now()->toISOString(),
            ],
            'status' => 'received',
            'error_details' => null,
            'ip_address' => fake()->ipv4(),
            'processed_payment_id' => null,
        ];
    }

    public function received(): static
    {
        return $this->state([
            'status' => 'received',
            'error_details' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => 'processing',
            'error_details' => null,
        ]);
    }

    public function success(): static
    {
        return $this->state([
            'status' => 'success',
            'error_details' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'error',
            'error_details' => fake()->randomElement([
                'Invalid payload structure',
                'Duplicate transaction',
                'Unable to match tenant',
                'Invalid signature',
            ]),
        ]);
    }

    public function forBank(string $bankCode): static
    {
        return $this->state(['bank_code' => $bankCode]);
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state([
            'processed_payment_id' => $payment->id,
            'status' => 'success',
        ]);
    }

    public function withPayload(array $payload): static
    {
        return $this->state(['payload' => $payload]);
    }

    public function equity(): static
    {
        return $this->state([
            'bank_code' => 'EQUITY',
            'event_type' => 'payment.received',
        ]);
    }

    public function kcb(): static
    {
        return $this->state([
            'bank_code' => 'KCB',
            'event_type' => 'payment.received',
        ]);
    }

    public function coop(): static
    {
        return $this->state([
            'bank_code' => 'COOP',
            'event_type' => 'account.credited',
        ]);
    }
}
