<?php

namespace App\Services;

use App\Models\PaymentConfiguration;
use App\Models\Unit;

/**
 * Water Rate Service - Determines effective water rates using 3-tier inheritance.
 *
 * WHY 3-tier hierarchy exists:
 * - Building-level overrides enable per-building pricing (e.g., premium buildings
 *   with higher maintenance costs can charge higher water rates than budget buildings)
 * - Landlord-level defaults provide centralized rate management without per-building config
 * - System fallback (150 KES/unit) ensures no null values leak through for new landlords
 *   who haven't configured their rates yet
 *
 * This design balances flexibility (landlords can customize per-building) with simplicity
 * (most landlords just set one rate for all buildings).
 */
class WaterRateService
{
    /**
     * Get the effective water rate for a unit.
     *
     * Rate hierarchy (most specific wins):
     * 1. Building override - if landlord has configured this specific building
     * 2. Landlord's PaymentConfiguration - central default for all buildings
     * 3. System config default - safety net for unconfigured landlords
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
