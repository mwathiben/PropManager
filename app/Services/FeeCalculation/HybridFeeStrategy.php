<?php

namespace App\Services\FeeCalculation;

use App\Models\PlatformBillingSetting;
use App\Models\User;

class HybridFeeStrategy implements FeeCalculationStrategy
{
    protected PlatformBillingSetting $settings;

    protected TransactionFeeStrategy $baseStrategy;

    public function __construct(?PlatformBillingSetting $settings = null)
    {
        $this->settings = $settings ?? PlatformBillingSetting::current();
        $this->baseStrategy = new TransactionFeeStrategy($this->settings);
    }

    /**
     * Calculate the platform fee based on subscription status
     */
    public function calculateFee(float $amount, User $landlord): FeeCalculationResult
    {
        // Check if landlord has an active subscription
        $subscription = $landlord->subscription ?? null;
        $hasActiveSubscription = $subscription && $subscription->isActive();

        if (! $hasActiveSubscription) {
            // No subscription - use full transaction fee
            $baseResult = $this->baseStrategy->calculateFee($amount, $landlord);

            return new FeeCalculationResult(
                grossAmount: $baseResult->grossAmount,
                feeAmount: $baseResult->feeAmount,
                netAmount: $baseResult->netAmount,
                percentageApplied: $baseResult->percentageApplied,
                feeType: 'hybrid',
                breakdown: array_merge($baseResult->breakdown, [
                    'has_subscription' => false,
                    'discount_applied' => 0,
                    'note' => 'Full transaction fee - no active subscription',
                ]),
            );
        }

        // Landlord has active subscription
        $discountPercentage = $this->settings->hybrid_subscription_discount;

        // If 100% discount, no fees at all
        if ($discountPercentage >= 100) {
            return FeeCalculationResult::noFee(
                amount: $amount,
                feeType: 'hybrid',
                breakdown: [
                    'has_subscription' => true,
                    'subscription_plan' => $subscription->plan->name ?? 'Active',
                    'discount_percentage' => 100,
                    'note' => 'Transaction fees waived for subscribers',
                ],
            );
        }

        // Apply discount to base fee calculation
        $baseResult = $this->baseStrategy->calculateFee($amount, $landlord);
        $discountedFee = $baseResult->feeAmount * (1 - ($discountPercentage / 100));

        // Apply reduced minimum fee for subscribers (half of normal minimum)
        $reducedMinimum = $this->settings->minimum_fee * 0.5;
        $finalFee = max($discountedFee, $reducedMinimum);
        $finalFee = min($finalFee, $amount);
        $finalFee = round($finalFee, 2);

        $netAmount = round($amount - $finalFee, 2);

        return new FeeCalculationResult(
            grossAmount: $amount,
            feeAmount: $finalFee,
            netAmount: $netAmount,
            percentageApplied: round($baseResult->percentageApplied * (1 - ($discountPercentage / 100)), 2),
            feeType: 'hybrid',
            breakdown: [
                'has_subscription' => true,
                'subscription_plan' => $subscription->plan->name ?? 'Active',
                'base_fee' => $baseResult->feeAmount,
                'discount_percentage' => $discountPercentage,
                'discounted_fee' => round($discountedFee, 2),
                'reduced_minimum' => $reducedMinimum,
                'final_fee' => $finalFee,
            ],
        );
    }

    /**
     * Get the strategy identifier
     */
    public function getIdentifier(): string
    {
        return 'hybrid';
    }
}
