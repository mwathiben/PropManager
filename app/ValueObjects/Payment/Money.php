<?php

declare(strict_types=1);

namespace App\ValueObjects\Payment;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency = 'KES',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    /**
     * Create from a float amount (e.g., 100.50 KES).
     */
    public static function fromFloat(float $amount, string $currency = 'KES'): self
    {
        return new self(
            amount: (int) round($amount * 100),
            currency: strtoupper($currency),
        );
    }

    /**
     * Create from smallest currency unit (cents/kobo/cents).
     */
    public static function fromSmallestUnit(int $amount, string $currency = 'KES'): self
    {
        return new self(
            amount: $amount,
            currency: strtoupper($currency),
        );
    }

    /**
     * Get amount in the major currency unit (e.g., shillings, dollars).
     */
    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    /**
     * Get amount in the smallest currency unit (cents/kobo).
     */
    public function toSmallestUnit(): int
    {
        return $this->amount;
    }

    /**
     * Convert to Paystack format (kobo - multiply by 100).
     */
    public function toPaystackAmount(): int
    {
        return $this->amount;
    }

    /**
     * Convert to M-Pesa format (whole shillings).
     */
    public function toMpesaAmount(): int
    {
        return (int) round($this->amount / 100);
    }

    /**
     * Format as currency string.
     */
    public function format(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->toFloat());
    }
}
