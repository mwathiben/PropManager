<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Building;
use App\Models\PaymentConfiguration;

/**
 * Single source of truth for the water-settings editor payload — the global
 * PaymentConfiguration defaults + per-building overrides that WaterRateService
 * actually bills from. Used by BOTH the standalone /water/settings page and the
 * Water hub's Settings tab so they render the identical canonical editor
 * (Phase-83 follow-up: unified the previously-divergent settings surfaces).
 */
class WaterSettingsData
{
    /**
     * @return array{buildings: \Illuminate\Support\Collection<int, Building>, globalSettings: array{water_billing_type: string, water_unit_rate: float, flat_water_rate: float}}
     */
    public static function forLandlord(int $landlordId): array
    {
        $buildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->select(
                'id', 'name', 'water_billing_type', 'water_flat_rate', 'water_unit_rate',
                'water_standing_charge', 'water_minimum_charge', 'water_sewerage_percent',
                'water_vat_percent', 'water_source',
            )
            ->withCount('units')
            ->orderBy('name')
            ->get();

        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        $defaultRate = (float) config('propmanager.water.default_rate', 150);

        return [
            'buildings' => $buildings,
            'globalSettings' => [
                'water_billing_type' => $config->water_billing_type ?? 'consumption',
                'water_unit_rate' => (float) ($config->water_unit_rate ?? $defaultRate),
                'flat_water_rate' => (float) ($config->flat_water_rate ?? 0),
                // Phase-87 tariff depth (global defaults; buildings inherit when null).
                'tiered_tariffs' => $config->tiered_tariffs ?? [],
                'water_standing_charge' => $config->water_standing_charge,
                'water_minimum_charge' => $config->water_minimum_charge,
                'water_sewerage_percent' => $config->water_sewerage_percent,
                'water_vat_percent' => $config->water_vat_percent,
                'water_source' => $config->water_source,
            ],
        ];
    }
}
