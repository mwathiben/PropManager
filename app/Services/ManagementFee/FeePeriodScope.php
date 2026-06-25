<?php

declare(strict_types=1);

namespace App\Services\ManagementFee;

use Carbon\CarbonInterface;

/**
 * Slice-2 PR-2.3b: the statement window the gathered period figures
 * (billed/scheduled/occupancy) for {@see FeePeriodContext} are read from — which
 * landlord, which of the owner's properties, and the period bounds. (Collected is
 * already computed by the statement and passed alongside, not re-gathered here.)
 */
final readonly class FeePeriodScope
{
    /** @param  array<int, int>  $propertyIds */
    public function __construct(
        public int $landlordId,
        public array $propertyIds,
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}
}
