<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $lease = Lease::factory()->create();
        $rentDue = $lease->rent_amount;
        $waterDue = fake()->optional(0.5)->numberBetween(0, 2000) ?? 0;
        $arrears = fake()->optional(0.2)->numberBetween(0, 5000) ?? 0;

        return [
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'invoice_number' => 'INV-'.date('Ym').'-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'rent_due' => $rentDue,
            'water_due' => $waterDue,
            'arrears' => $arrears,
            'wallet_applied' => 0,
            'total_due' => $rentDue + $waterDue + $arrears,
            'amount_paid' => 0,
            'currency' => 'KES',
            'status' => 'draft',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent']);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'paid',
            'amount_paid' => $attrs['total_due'],
        ]);
    }

    public function partial(float $paidPercentage = 0.5): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'partial',
            'amount_paid' => $attrs['total_due'] * $paidPercentage,
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => 'overdue',
            'due_date' => now()->subDays(7),
        ]);
    }

    public function forLease(Lease $lease): static
    {
        return $this->state([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'rent_due' => $lease->rent_amount,
            'total_due' => $lease->rent_amount,
        ]);
    }

    public function withCurrency(Currency $currency): static
    {
        return $this->state([
            'currency' => $currency->value,
        ]);
    }
}
