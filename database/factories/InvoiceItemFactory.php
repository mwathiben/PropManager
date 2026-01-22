<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(1000, 50000);
        $quantity = 1;

        return [
            'invoice_id' => Invoice::factory(),
            'item_type' => InvoiceItem::TYPE_RENT,
            'description' => 'Monthly Rent',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $unitPrice * $quantity,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }

    public function rent(?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(15000, 50000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_RENT,
            'description' => 'Monthly Rent',
            'unit_price' => $price,
            'total' => $price,
            'sort_order' => 0,
        ]);
    }

    public function deposit(?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(15000, 50000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_DEPOSIT,
            'description' => 'Security Deposit',
            'unit_price' => $price,
            'total' => $price,
            'sort_order' => 1,
        ]);
    }

    public function water(?float $units = null, float $rate = 150): static
    {
        $quantity = $units ?? fake()->numberBetween(1, 20);
        $total = round($quantity * $rate, 2);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_WATER,
            'description' => "Water Charges ({$quantity} units @ Ksh {$rate})",
            'quantity' => $quantity,
            'unit_price' => $rate,
            'total' => $total,
            'sort_order' => 2,
            'metadata' => ['units' => $quantity, 'rate' => $rate],
        ]);
    }

    public function lateFee(?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(500, 5000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_LATE_FEE,
            'description' => 'Late Payment Fee',
            'unit_price' => $price,
            'total' => $price,
            'sort_order' => 5,
        ]);
    }

    public function arrears(?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(1000, 20000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_ARREARS,
            'description' => 'Previous Balance',
            'unit_price' => $price,
            'total' => $price,
            'sort_order' => 4,
        ]);
    }

    public function credit(?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(500, 5000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_CREDIT,
            'description' => 'Credit Applied',
            'unit_price' => -$price,
            'total' => -$price,
            'sort_order' => 10,
        ]);
    }

    public function other(?string $description = null, ?float $amount = null): static
    {
        $price = $amount ?? fake()->numberBetween(500, 5000);

        return $this->state([
            'item_type' => InvoiceItem::TYPE_OTHER,
            'description' => $description ?? 'Additional Charges',
            'unit_price' => $price,
            'total' => $price,
            'sort_order' => 6,
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(['invoice_id' => $invoice->id]);
    }
}
