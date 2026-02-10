<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        return [
            'landlord_id' => null,
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_id' => strtoupper(fake()->bothify('QKL#########')),
            'event_type' => fake()->randomElement([
                'stk_callback', 'c2b_confirmation', 'till_confirmation',
                'charge.success', 'payment.complete', 'payment.received',
            ]),
            'payload_hash' => hash('sha256', json_encode(fake()->words(5))),
            'retry_count' => 1,
            'first_received_at' => now(),
            'last_received_at' => now(),
            'status' => WebhookLog::STATUS_PENDING,
            'processing_time_ms' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function processed(): static
    {
        return $this->state([
            'status' => WebhookLog::STATUS_PROCESSED,
            'processing_time_ms' => fake()->numberBetween(50, 2000),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => WebhookLog::STATUS_FAILED,
            'processing_time_ms' => fake()->numberBetween(50, 5000),
        ]);
    }

    public function mpesa(): static
    {
        return $this->state([
            'provider' => WebhookLog::PROVIDER_MPESA,
            'event_type' => 'stk_callback',
        ]);
    }

    public function intasend(): static
    {
        return $this->state([
            'provider' => WebhookLog::PROVIDER_INTASEND,
            'event_type' => 'payment.complete',
        ]);
    }

    public function paystack(): static
    {
        return $this->state([
            'provider' => WebhookLog::PROVIDER_PAYSTACK,
            'event_type' => 'charge.success',
        ]);
    }

    public function bank(string $bankCode = 'EQUITY'): static
    {
        return $this->state([
            'provider' => WebhookLog::PROVIDER_BANK,
            'event_type' => 'payment.received',
            'event_id' => "bank:{$bankCode}:".strtoupper(fake()->bothify('TXN########')),
        ]);
    }

    public function withRetries(int $count): static
    {
        return $this->state([
            'retry_count' => $count,
            'first_received_at' => now()->subMinutes($count * 5),
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function withProcessingTime(int $ms): static
    {
        return $this->state(['processing_time_ms' => $ms]);
    }
}
