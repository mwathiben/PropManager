<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $lease = Lease::factory()->create();
        $amount = fake()->numberBetween(500, 5000);

        return [
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_after' => $amount,
            'reason' => 'Overpayment credited to wallet',
            'invoice_id' => null,
            'payment_id' => null,
        ];
    }

    public function credit(?float $amount = null): static
    {
        $amt = $amount ?? fake()->numberBetween(500, 5000);

        return $this->state(fn (array $attrs) => [
            'type' => 'credit',
            'amount' => $amt,
            'balance_after' => ($attrs['balance_after'] ?? 0) + $amt,
            'reason' => fake()->randomElement([
                'Overpayment credited to wallet',
                'Refund credited',
                'Adjustment credit',
                'Goodwill credit',
            ]),
        ]);
    }

    public function debit(?float $amount = null): static
    {
        $amt = $amount ?? fake()->numberBetween(500, 5000);

        return $this->state(fn (array $attrs) => [
            'type' => 'debit',
            'amount' => $amt,
            'balance_after' => max(0, ($attrs['balance_after'] ?? 0) - $amt),
            'reason' => fake()->randomElement([
                'Applied to invoice',
                'Rent payment from wallet',
                'Balance adjustment',
            ]),
        ]);
    }

    public function fromPayment(Payment $payment): static
    {
        return $this->state([
            'type' => 'credit',
            'amount' => $payment->amount,
            'payment_id' => $payment->id,
            'landlord_id' => $payment->landlord_id,
            'reason' => 'Overpayment from payment #'.$payment->reference,
        ]);
    }

    public function appliedToInvoice(Invoice $invoice, ?float $amount = null): static
    {
        return $this->state([
            'type' => 'debit',
            'amount' => $amount ?? fake()->numberBetween(500, 5000),
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'reason' => 'Applied to invoice '.$invoice->invoice_number,
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'balance_after' => $lease->wallet_balance ?? 0,
        ]);
    }
}
