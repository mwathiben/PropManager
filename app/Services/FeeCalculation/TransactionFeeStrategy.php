<?php

namespace App\Services\FeeCalculation;

use App\Models\Payment;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformFeeTier;
use App\Models\User;

class TransactionFeeStrategy implements FeeCalculationStrategy
{
    protected PlatformBillingSetting $settings;

    public function __construct(?PlatformBillingSetting $settings = null)
    {
        $this->settings = $settings ?? PlatformBillingSetting::current();
    }

    public function calculateFee(float $amount, User $landlord): FeeCalculationResult
    {
        $rateInfo = $this->resolvePercentage($landlord);
        $percentage = $rateInfo['percentage'];
        $calculatedFee = ($amount * $percentage) / 100;

        $minimumApplied = false;
        if ($calculatedFee < $this->settings->minimum_fee) {
            $fee = $this->settings->minimum_fee;
            $minimumApplied = true;
        } else {
            $fee = $calculatedFee;
        }

        $maximumApplied = false;
        if ($this->settings->maximum_fee && $fee > $this->settings->maximum_fee) {
            $fee = $this->settings->maximum_fee;
            $maximumApplied = true;
        }

        $fee = min($fee, $amount);
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
                'rate_source' => $rateInfo['source'],
                'tier_name' => $rateInfo['tier_name'],
                'mtd_volume' => $rateInfo['mtd_volume'],
            ],
        );
    }

    public function getIdentifier(): string
    {
        return 'transaction_fee';
    }

    private function resolvePercentage(User $landlord): array
    {
        if (! PlatformFeeTier::active()->exists()) {
            return [
                'percentage' => (float) $this->settings->transaction_fee_percentage,
                'source' => 'flat',
                'tier_name' => null,
                'mtd_volume' => 0,
            ];
        }

        $mtdVolume = (float) Payment::where('landlord_id', $landlord->id)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->where('is_voided', false)
            ->sum('amount');

        $tier = PlatformFeeTier::forVolume($mtdVolume);

        if (! $tier) {
            return [
                'percentage' => (float) $this->settings->transaction_fee_percentage,
                'source' => 'flat',
                'tier_name' => null,
                'mtd_volume' => $mtdVolume,
            ];
        }

        return [
            'percentage' => (float) $tier->fee_percentage,
            'source' => 'tiered',
            'tier_name' => $tier->name,
            'mtd_volume' => $mtdVolume,
        ];
    }
}
