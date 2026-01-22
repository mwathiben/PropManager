<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        $payment = Payment::factory()->create();
        $invoice = Invoice::find($payment->invoice_id);

        return [
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $payment->landlord_id,
            'receipt_number' => 'RCP-'.strtoupper(fake()->unique()->bothify('??????')),
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'notes' => fake()->optional(0.3)->sentence(),
            'is_partial' => false,
            'issued_at' => now(),
            'emailed_at' => null,
            'pdf_path' => null,
        ];
    }

    public function partial(): static
    {
        return $this->state(['is_partial' => true]);
    }

    public function emailed(): static
    {
        return $this->state(['emailed_at' => now()]);
    }

    public function withPdf(): static
    {
        return $this->state(fn (array $attrs) => [
            'pdf_path' => 'receipts/'.$attrs['landlord_id'].'/'.($attrs['receipt_number'] ?? 'RCP-'.fake()->uuid()).'.pdf',
        ]);
    }

    public function forPayment(Payment $payment): static
    {
        $invoice = $payment->invoice;

        return $this->state([
            'payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $payment->landlord_id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
        ]);
    }
}
