<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\QueuedPaymentIntent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QueuedPaymentIntentFactory extends Factory
{
    protected $model = QueuedPaymentIntent::class;

    public function definition(): array
    {
        $invoice = Invoice::factory()->sent()->create();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $invoice->landlord_id,
        ]);
        $nonce = Str::uuid()->toString();

        return [
            'idempotency_key' => QueuedPaymentIntent::generateIdempotencyKey(
                $tenant->id,
                $invoice->id,
                $nonce,
            ),
            'tenant_id' => $tenant->id,
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'amount' => $invoice->total_due,
            'currency' => 'KES',
            'payment_method' => fake()->randomElement(['mobile_money', 'mpesa', 'paystack']),
            'phone_number' => '254'.fake()->numerify('7########'),
            'status' => QueuedPaymentIntent::STATUS_PENDING,
            'attempts' => 0,
            'last_attempt_at' => null,
            'next_retry_at' => null,
            'expires_at' => now()->addHours(24),
            'failure_reason' => null,
            'metadata' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => QueuedPaymentIntent::STATUS_PENDING,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => QueuedPaymentIntent::STATUS_PROCESSING,
            'attempts' => 1,
            'last_attempt_at' => now(),
            'next_retry_at' => now()->addSeconds(10),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => QueuedPaymentIntent::STATUS_COMPLETED,
            'attempts' => 1,
            'last_attempt_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => QueuedPaymentIntent::STATUS_FAILED,
            'attempts' => 3,
            'last_attempt_at' => now(),
            'failure_reason' => fake()->randomElement([
                'Insufficient balance',
                'Request timeout',
                'Provider unavailable',
                'Wrong PIN entered',
            ]),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => QueuedPaymentIntent::STATUS_EXPIRED,
            'expires_at' => now()->subHour(),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'amount' => $invoice->total_due,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state([
            'metadata' => $metadata,
        ]);
    }
}
