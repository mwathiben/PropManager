<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LateFeeFactory extends Factory
{
    protected $model = LateFee::class;

    public function definition(): array
    {
        $invoice = Invoice::factory()->create();
        $feeAmount = fake()->numberBetween(500, 5000);

        return [
            'invoice_id' => $invoice->id,
            'late_fee_policy_id' => LateFeePolicy::factory()->forLandlord(User::find($invoice->landlord_id)),
            'landlord_id' => $invoice->landlord_id,
            'fee_amount' => $feeAmount,
            'cumulative_total' => $feeAmount,
            'applied_date' => now(),
            'days_overdue' => fake()->numberBetween(1, 30),
            'is_waived' => false,
            'waived_by' => null,
            'waived_at' => null,
            'waiver_reason' => null,
        ];
    }

    public function waived(?User $waivedBy = null): static
    {
        return $this->state(fn (array $attrs) => [
            'is_waived' => true,
            'waived_by' => $waivedBy?->id ?? User::find($attrs['landlord_id'])?->id,
            'waived_at' => now(),
            'waiver_reason' => fake()->randomElement([
                'First-time late payment',
                'Hardship situation',
                'Payment processing delay',
                'Customer goodwill',
            ]),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state([
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
        ]);
    }

    public function withPolicy(LateFeePolicy $policy): static
    {
        return $this->state([
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $policy->landlord_id,
        ]);
    }
}
