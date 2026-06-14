<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

/**
 * The period figures a management fee can be charged against, computed once by
 * the caller (statement/ledger) and handed to the calculator so the fee maths
 * stays pure and unit-testable.
 *
 * - collected / billed / scheduled feed the percentage bases.
 * - occupancyWeightedUnits feeds a per-unit flat fee: the sum, across the
 *   owner's units, of each unit's occupied share of the period (a unit occupied
 *   for half the period contributes 0.5).
 */
final readonly class FeePeriodContext
{
    public function __construct(
        public float $collected = 0.0,
        public float $billed = 0.0,
        public float $scheduled = 0.0,
        public float $occupancyWeightedUnits = 0.0,
    ) {}
}
