<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * One building's worth of units: a floors x unitsPerFloor grid sharing a name
 * and (optional) unit-number prefix. The onboarding structure step describes a
 * property as one of these for a single building, or a zeroed container plus one
 * per wing.
 */
final class UnitBlockSpec
{
    public function __construct(
        public readonly string $name,
        public readonly string $prefix,
        public readonly int $floors,
        public readonly int $unitsPerFloor,
    ) {}
}
