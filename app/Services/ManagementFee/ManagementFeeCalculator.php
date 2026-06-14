<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

use App\Models\PropertyOwner;

/**
 * Computes the management fee a manager earns on an owner's portfolio for a
 * period, from the fee model configured on the management relationship.
 *
 * Pure: the period figures (collected/billed/scheduled/occupancy) arrive
 * pre-computed in the {@see FeePeriodContext}; this class only applies the
 * configured shape. The percentage clamp mirrors {@see PropertyOwner::managementFeeOn()}
 * so a bad imported rate can never drive the owner's net negative.
 */
final class ManagementFeeCalculator
{
    public function calculate(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        return round(match ($relationship->management_fee_type) {
            'percentage' => $this->percentageFee($relationship, $context),
            'flat' => $this->flatFee($relationship, $context),
            default => 0.0,
        }, 2);
    }

    private function percentageFee(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        $base = match ($relationship->management_fee_base) {
            'billed' => $context->billed,
            'scheduled' => $context->scheduled,
            default => $context->collected,
        };

        return $base * min((float) $relationship->management_fee_value, 100.0) / 100;
    }

    private function flatFee(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        $value = (float) $relationship->management_fee_value;

        return match ($relationship->management_fee_flat_cadence) {
            'per_unit' => $value * $context->occupancyWeightedUnits,
            default => $value,
        };
    }
}
