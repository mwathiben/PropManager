<?php

namespace Database\Factories;

use App\Models\BankReconciliationQueue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankReconciliationQueueFactory extends Factory
{
    protected $model = BankReconciliationQueue::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'payment_id' => null,
            'bank_code' => fake()->randomElement(['EQUITY', 'KCB', 'COOP', 'NCBA', 'STANBIC', 'ABSA']),
            'transaction_reference' => fake()->unique()->regexify('TXN[A-Z0-9]{10}'),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'status' => fake()->randomElement(['pending', 'matched', 'unmatched', 'error']),
            'matched_invoice_id' => null,
            'error_message' => null,
            'raw_payload' => [
                'account_number' => fake()->numerify('##########'),
                'transaction_date' => now()->toDateString(),
                'description' => fake()->sentence(3),
                'reference' => fake()->regexify('[A-Z0-9]{8}'),
            ],
            'matched_at' => null,
            'retry_count' => 0,
            'next_retry_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'matched_invoice_id' => null,
            'payment_id' => null,
            'matched_at' => null,
            'error_message' => null,
        ]);
    }

    public function matched(): static
    {
        return $this->state([
            'status' => 'matched',
            'matched_invoice_id' => Invoice::factory(),
            'payment_id' => Payment::factory(),
            'matched_at' => now(),
            'error_message' => null,
        ]);
    }

    public function unmatched(): static
    {
        return $this->state([
            'status' => 'unmatched',
            'matched_invoice_id' => null,
            'payment_id' => null,
            'matched_at' => null,
            'error_message' => null,
        ]);
    }

    public function error(string $message = 'Matching failed'): static
    {
        return $this->state([
            'status' => 'error',
            'error_message' => $message,
            'retry_count' => fake()->numberBetween(1, 2),
            'next_retry_at' => now()->addMinutes(10),
        ]);
    }

    public function maxRetriesExceeded(): static
    {
        return $this->state([
            'status' => 'error',
            'error_message' => 'Max retries exceeded',
            'retry_count' => 3,
            'next_retry_at' => null,
        ]);
    }

    public function retryable(): static
    {
        return $this->state([
            'status' => 'error',
            'error_message' => 'Temporary failure',
            'retry_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);
    }

    public function forBank(string $bankCode): static
    {
        return $this->state(['bank_code' => $bankCode]);
    }

    public function equity(): static
    {
        return $this->forBank('EQUITY');
    }

    public function kcb(): static
    {
        return $this->forBank('KCB');
    }

    public function cooperative(): static
    {
        return $this->forBank('COOP');
    }

    public function withAmount(float $amount): static
    {
        return $this->state(['amount' => $amount]);
    }

    public function withPayload(array $payload): static
    {
        return $this->state(['raw_payload' => $payload]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'matched_invoice_id' => $invoice->id,
            'status' => 'matched',
            'matched_at' => now(),
        ]);
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(['payment_id' => $payment->id]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
