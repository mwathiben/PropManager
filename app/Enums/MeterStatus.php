<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phase-86 WATER-METER-FOUNDATION: lifecycle states for a physical water meter.
 * Only `active` meters accept new readings; `replaced` rows retain history and
 * point at their successor via replaced_by_meter_id.
 */
enum MeterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Faulty = 'faulty';
    case Replaced = 'replaced';
    case Decommissioned = 'decommissioned';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
