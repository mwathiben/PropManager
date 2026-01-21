<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use App\Models\Unit;

class WaterRateService
{
    /**
     * Get the effective water rate for a unit.
     *
     * Rate hierarchy:
     * 1. Building override (if set)
     * 2. Landlord's PaymentConfiguration (if set)
     * 3. System default from config
     */
    public function getEffectiveRate(Unit $unit): float
    {
        $building = $unit->building;

        if ($building->water_unit_rate !== null) {
            return (float) $building->water_unit_rate;
        }

        $landlordId = $building->property->landlord_id;
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if ($config?->water_unit_rate !== null) {
            return (float) $config->water_unit_rate;
        }

        return (float) config('propmanager.water.default_rate', 150);
    }

    /**
     * Get the effective rate for a building (without unit context).
     */
    public function getEffectiveRateForBuilding(\App\Models\Building $building): float
    {
        if ($building->water_unit_rate !== null) {
            return (float) $building->water_unit_rate;
        }

        $landlordId = $building->property->landlord_id;
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if ($config?->water_unit_rate !== null) {
            return (float) $config->water_unit_rate;
        }

        return (float) config('propmanager.water.default_rate', 150);
    }

    /**
     * Get the system default rate from config.
     */
    public function getDefaultRate(): float
    {
        return (float) config('propmanager.water.default_rate', 150);
    }
}
