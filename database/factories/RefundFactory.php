<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $payment = Payment::factory()->create();
        $landlord = User::find($payment->landlord_id);

        return [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'landlord_id' => $payment->landlord_id,
            'amount' => fake()->numberBetween(500, min(5000, (int) $payment->amount)),
            'status' => 'pending',
            'reason' => fake()->randomElement([
                'Overpayment',
                'Service not rendered',
                'Billing error',
                'Customer request',
                'Duplicate payment',
            ]),
            'payment_method' => $payment->payment_method,
            'paystack_refund_reference' => null,
            'mpesa_conversation_id' => null,
            'mpesa_transaction_id' => null,
            'initiated_by' => $landlord->id,
            'approved_by' => null,
            'processed_at' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'error_details' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'approved_by' => null,
            'processed_at' => null,
        ]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'approved',
            'approved_by' => $approver?->id ?? User::find($attrs['landlord_id'])?->id,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'processing',
            'approved_by' => User::find($attrs['landlord_id'])?->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'completed',
            'approved_by' => User::find($attrs['landlord_id'])?->id,
            'processed_at' => now(),
        ]);
    }

    public function failed(?array $errorDetails = null): static
    {
        return $this->state([
            'status' => 'failed',
            'error_details' => $errorDetails ?? ['code' => 'FAILED', 'message' => 'Refund processing failed'],
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function viaPaystack(): static
    {
        return $this->state([
            'payment_method' => 'paystack',
            'paystack_refund_reference' => 'RF_'.strtoupper(fake()->unique()->bothify('??????????')),
        ]);
    }

    public function viaMpesa(): static
    {
        return $this->state([
            'payment_method' => 'mobile_money',
            'mpesa_conversation_id' => 'AG_'.fake()->unique()->numerify('##############'),
            'mpesa_transaction_id' => 'QKL'.fake()->unique()->numerify('#########'),
        ]);
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state([
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'landlord_id' => $payment->landlord_id,
            'payment_method' => $payment->payment_method,
            'amount' => min((int) $payment->amount, fake()->numberBetween(500, 5000)),
        ]);
    }
}
