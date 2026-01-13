<?php

namespace App\Services\FeeCalculation;

use App\Models\User;

class SubscriptionOnlyStrategy implements FeeCalculationStrategy
{
    /**
     * Calculate the platform fee - always zero for subscription model
     */
    public function calculateFee(float $amount, User $landlord): FeeCalculationResult
    {
        return FeeCalculationResult::noFee(
            amount: $amount,
            feeType: 'subscription_flat',
            breakdown: [
                'note' => 'Transaction fees waived - subscription model active',
                'model' => 'subscription_only',
            ],
        );
    }

    /**
     * Get the strategy identifier
     */
    public function getIdentifier(): string
    {
        return 'subscription';
    }
}
