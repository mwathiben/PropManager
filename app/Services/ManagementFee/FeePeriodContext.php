<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

/**
 * The period figures a management fee can be charged against, computed once by
 * the caller (statement/ledger) and handed to the calculator so the fee maths
 * stays pure and unit-testable.
 *
 * - collected / billed / scheduled feed the percentage bases.
 * - occupiedUnits feeds a per-unit flat fee: the count of the owner's units that
 *   were occupied at any point in the period. Each is charged the full flat
 *   amount — the manager does the same work (the move-out included) whether a
 *   unit was occupied for the whole period or only part of it.
 */
final readonly class FeePeriodContext
{
    public function __construct(
        public float $collected = 0.0,
        public float $billed = 0.0,
        public float $scheduled = 0.0,
        public int $occupiedUnits = 0,
    ) {
        if ($collected < 0.0 || $billed < 0.0 || $scheduled < 0.0 || $occupiedUnits < 0) {
            throw new \InvalidArgumentException(
                'FeePeriodContext figures must be non-negative; got collected='.$collected
                .', billed='.$billed.', scheduled='.$scheduled.', occupiedUnits='.$occupiedUnits.'.'
            );
        }
    }
}
