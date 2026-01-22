<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        $invoice = Invoice::factory()->create();
        $lease = $invoice->lease;

        return [
            'landlord_id' => $invoice->landlord_id,
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'invoice_id' => $invoice->id,
            'applied_to_invoice_id' => null,
            'credit_number' => CreditNote::generateCreditNumber(),
            'amount' => fake()->numberBetween(500, 10000),
            'applied_amount' => 0,
            'reason' => fake()->randomElement([
                CreditNote::REASON_OVERPAYMENT,
                CreditNote::REASON_BILLING_ERROR,
                CreditNote::REASON_GOODWILL,
                CreditNote::REASON_DUPLICATE_CHARGE,
                CreditNote::REASON_SERVICE_ISSUE,
                CreditNote::REASON_OTHER,
            ]),
            'notes' => fake()->optional(0.5)->sentence(),
            'status' => CreditNote::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'applied_at' => null,
            'voided_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => CreditNote::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => CreditNote::STATUS_APPROVED,
            'approved_by' => $approver?->id ?? User::find($attrs['landlord_id'])?->id,
            'approved_at' => now(),
        ]);
    }

    public function applied(?Invoice $toInvoice = null): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => CreditNote::STATUS_APPLIED,
            'applied_amount' => $attrs['amount'],
            'applied_to_invoice_id' => $toInvoice?->id,
            'applied_at' => now(),
            'approved_by' => User::find($attrs['landlord_id'])?->id,
            'approved_at' => now()->subHour(),
        ]);
    }

    public function voided(): static
    {
        return $this->state([
            'status' => CreditNote::STATUS_VOIDED,
            'voided_at' => now(),
        ]);
    }

    public function overpayment(): static
    {
        return $this->state(['reason' => CreditNote::REASON_OVERPAYMENT]);
    }

    public function billingError(): static
    {
        return $this->state(['reason' => CreditNote::REASON_BILLING_ERROR]);
    }

    public function goodwill(): static
    {
        return $this->state(['reason' => CreditNote::REASON_GOODWILL]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'lease_id' => $invoice->lease_id,
            'tenant_id' => $invoice->lease->tenant_id,
            'landlord_id' => $invoice->landlord_id,
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'landlord_id' => $lease->landlord_id,
        ]);
    }
}
