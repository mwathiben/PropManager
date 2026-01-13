<?php

namespace App\Services\FeeCalculation;

use App\Models\PlatformBillingSetting;
use App\Models\User;

class TransactionFeeStrategy implements FeeCalculationStrategy
{
    protected PlatformBillingSetting $settings;

    public function __construct(?PlatformBillingSetting $settings = null)
    {
        $this->settings = $settings ?? PlatformBillingSetting::current();
    }

    /**
     * Calculate the platform fee for a given payment amount
     */
    public function calculateFee(float $amount, User $landlord): FeeCalculationResult
    {
        $percentage = $this->settings->transaction_fee_percentage;
        $calculatedFee = ($amount * $percentage) / 100;

        // Apply minimum fee
        $minimumApplied = false;
        if ($calculatedFee < $this->settings->minimum_fee) {
            $fee = $this->settings->minimum_fee;
            $minimumApplied = true;
        } else {
            $fee = $calculatedFee;
        }

        // Apply maximum fee cap if set
        $maximumApplied = false;
        if ($this->settings->maximum_fee && $fee > $this->settings->maximum_fee) {
            $fee = $this->settings->maximum_fee;
            $maximumApplied = true;
        }

        // Ensure fee doesn't exceed payment amount
        $fee = min($fee, $amount);

        // Round to 2 decimal places
        $fee = round($fee, 2);
        $netAmount = round($amount - $fee, 2);

        return new FeeCalculationResult(
            grossAmount: $amount,
            feeAmount: $fee,
            netAmount: $netAmount,
            percentageApplied: $percentage,
            feeType: 'transaction_percentage',
            breakdown: [
                'base_percentage' => $percentage,
                'calculated_fee' => round($calculatedFee, 2),
                'minimum_fee' => $this->settings->minimum_fee,
                'maximum_fee' => $this->settings->maximum_fee,
                'minimum_applied' => $minimumApplied,
                'maximum_applied' => $maximumApplied,
            ],
        );
    }

    /**
     * Get the strategy identifier
     */
    public function getIdentifier(): string
    {
        return 'transaction_fee';
    }
}
