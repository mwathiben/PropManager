<?php

namespace App\Services\Invoice;

use App\Models\InvoiceItem;
use App\Models\Lease;

class FirstInvoiceItemBuilder
{
    private array $items = [];

    private Lease $lease;

    private mixed $settings;

    private array $overrides = [];

    public static function forLease(Lease $lease): self
    {
        $builder = new self;
        $builder->lease = $lease;

        return $builder;
    }

    public function withSettings(mixed $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function withOverrides(array $overrides): self
    {
        $this->overrides = $overrides;

        return $this;
    }

    public function addRentItem(float $amount, string $description): self
    {
        $this->addItem(InvoiceItem::TYPE_RENT, $description, $amount);

        return $this;
    }

    public function addDepositItem(): self
    {
        $deposit = $this->overrides['deposit'] ?? $this->lease->deposit_amount ?? 0;
        $this->addItem(InvoiceItem::TYPE_DEPOSIT, 'Security Deposit', $deposit);

        return $this;
    }

    public function addLastMonthRentItem(): self
    {
        $includeLastMonth = $this->overrides['include_last_month_rent']
            ?? $this->settings->include_last_month_rent
            ?? false;

        if ($includeLastMonth) {
            $lastMonthRent = $this->overrides['last_month_rent'] ?? $this->lease->rent_amount ?? 0;
            $this->addItem(InvoiceItem::TYPE_RENT, 'Last Month Rent (Advance)', $lastMonthRent);
        }

        return $this;
    }

    public function addAdminFeeItem(): self
    {
        $adminFee = $this->overrides['admin_fee'] ?? $this->settings->admin_fee_amount ?? 0;
        $this->addItem(InvoiceItem::TYPE_ADMIN_FEE, 'Administrative/Processing Fee', $adminFee);

        return $this;
    }

    public function addKeyDepositItem(): self
    {
        $keyDeposit = $this->overrides['key_deposit'] ?? $this->settings->key_deposit_amount ?? 0;
        $this->addItem(InvoiceItem::TYPE_KEY_DEPOSIT, 'Key Deposit', $keyDeposit);

        return $this;
    }

    public function addOtherCharges(): self
    {
        $otherCharges = $this->overrides['other_charges'] ?? [];

        foreach ($otherCharges as $charge) {
            $amount = $charge['amount'] ?? 0;
            if ($amount > 0) {
                $this->items[] = [
                    'type' => InvoiceItem::TYPE_OTHER,
                    'description' => $charge['description'] ?? 'Other Charge',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ];
            }
        }

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    private function addItem(string $type, string $description, float $amount): void
    {
        if ($amount > 0) {
            $this->items[] = [
                'type' => $type,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ];
        }
    }
}
