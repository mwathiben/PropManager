<?php

namespace App\Services\FeeCalculation;

use App\Models\User;

interface FeeCalculationStrategy
{
    /**
     * Calculate the platform fee for a given payment amount
     *
     * @param  float  $amount  The gross payment amount
     * @param  User  $landlord  The landlord receiving payment
     */
    public function calculateFee(float $amount, User $landlord): FeeCalculationResult;

    /**
     * Get the strategy identifier
     */
    public function getIdentifier(): string;
}
