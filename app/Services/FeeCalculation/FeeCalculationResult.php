<?php

namespace App\Services\FeeCalculation;

class FeeCalculationResult
{
    public function __construct(
        public readonly float $grossAmount,
        public readonly float $feeAmount,
        public readonly float $netAmount,
        public readonly float $percentageApplied,
        public readonly string $feeType,
        public readonly array $breakdown = [],
    ) {}

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'gross_amount' => $this->grossAmount,
            'fee_amount' => $this->feeAmount,
            'net_amount' => $this->netAmount,
            'percentage_applied' => $this->percentageApplied,
            'fee_type' => $this->feeType,
            'breakdown' => $this->breakdown,
        ];
    }

    /**
     * Check if there is a fee
     */
    public function hasFee(): bool
    {
        return $this->feeAmount > 0;
    }

    /**
     * Get fee as percentage of gross
     */
    public function getEffectiveFeePercentage(): float
    {
        if ($this->grossAmount <= 0) {
            return 0;
        }

        return round(($this->feeAmount / $this->grossAmount) * 100, 2);
    }

    /**
     * Create a zero-fee result
     */
    public static function noFee(float $amount, string $feeType = 'subscription_flat', array $breakdown = []): self
    {
        return new self(
            grossAmount: $amount,
            feeAmount: 0,
            netAmount: $amount,
            percentageApplied: 0,
            feeType: $feeType,
            breakdown: $breakdown ?: ['note' => 'No fee applied'],
        );
    }
}
