<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
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
    /** Maximum allowable percentage rate — prevents negative owner net. */
    public const float MAX_PERCENTAGE = 100.0;

    public function calculate(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        return round(match ($relationship->management_fee_type) {
            ManagementFeeType::Percentage => $this->percentageFee($relationship, $context),
            ManagementFeeType::Flat => $this->flatFee($relationship, $context),
            ManagementFeeType::None => 0.0,
        }, 2);
    }

    private function percentageFee(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        $base = match ($relationship->management_fee_base) {
            ManagementFeeBase::Billed => $context->billed,
            ManagementFeeBase::Scheduled => $context->scheduled,
            ManagementFeeBase::Collected, null => $context->collected,
        };

        return $base * max(0.0, min((float) $relationship->management_fee_value, self::MAX_PERCENTAGE)) / 100;
    }

    private function flatFee(PropertyOwner $relationship, FeePeriodContext $context): float
    {
        $value = (float) $relationship->management_fee_value;

        return match ($relationship->management_fee_flat_cadence) {
            ManagementFeeFlatCadence::PerUnit => $value * $context->occupiedUnits,
            ManagementFeeFlatCadence::PerPeriod, null => $value,
        };
    }
}
