<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->uuid(),
            'request_hash' => hash('sha256', fake()->text()),
            'status' => 'pending',
            'response_data' => null,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => 'processing',
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'response_data' => ['status' => 'success'],
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'response_data' => ['error' => 'Processing failed'],
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subHours(25),
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'expires_at' => now()->addHours(23),
        ]);
    }

    public function withResponse(array $response): static
    {
        return $this->state([
            'response_data' => $response,
        ]);
    }

    public function mpesaKey(string $receiptNumber): static
    {
        return $this->state([
            'key' => 'mpesa:'.$receiptNumber,
        ]);
    }

    public function intasendKey(string $reference): static
    {
        return $this->state([
            'key' => 'intasend:'.$reference,
        ]);
    }

    public function paystackKey(string $reference): static
    {
        return $this->state([
            'key' => 'paystack:'.$reference,
        ]);
    }
}
