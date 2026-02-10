<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $invoice = Invoice::factory()->sent()->create();

        return [
            'invoice_id' => $invoice->id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $invoice->landlord_id,
            'amount' => $invoice->total_due,
            'currency' => 'KES',
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money', 'paystack']),
            'payment_date' => now(),
            'reference' => 'PAY-'.strtoupper(fake()->unique()->bothify('??######')),
        ];
    }

    public function cash(): static
    {
        return $this->state([
            'payment_method' => 'cash',
            'reference' => 'CASH-'.strtoupper(fake()->unique()->bothify('??######')),
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state([
            'payment_method' => 'bank_transfer',
            'reference' => 'BNK-'.strtoupper(fake()->unique()->bothify('??######')),
        ]);
    }

    public function mpesa(): static
    {
        return $this->state([
            'payment_method' => 'mobile_money',
            'mpesa_transaction_id' => 'QKL'.fake()->unique()->numberBetween(100000000, 999999999),
            'mpesa_checkout_request_id' => 'ws_CO_'.fake()->numerify('##############'),
        ]);
    }

    public function paystack(): static
    {
        return $this->state([
            'payment_method' => 'paystack',
            'paystack_reference' => 'PSK_'.fake()->unique()->uuid(),
        ]);
    }

    public function intasend(): static
    {
        return $this->state([
            'payment_method' => 'mobile_money',
            'intasend_transaction_id' => strtoupper(fake()->unique()->bothify('???????')),
            'intasend_reference' => 'ITS-'.time().'-'.strtoupper(substr(uniqid(), -6)),
        ]);
    }

    public function forInvoice(Invoice $invoice, ?float $amount = null): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'lease_id' => $invoice->lease_id,
            'landlord_id' => $invoice->landlord_id,
            'amount' => $amount ?? $invoice->total_due,
        ]);
    }

    public function withCurrency(Currency $currency): static
    {
        return $this->state([
            'currency' => $currency->value,
        ]);
    }
}
