<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use Illuminate\Support\Collection;

/**
 * Phase-88 WATER-READING-CYCLE: resolves the per-building reading/review cadence
 * (building override -> landlord PaymentConfiguration -> default) for the
 * reminder + auto-approve crons. Queries are landlord-scope-free because the
 * crons run without an authenticated user; ownership is via explicit landlord_id.
 */
class WaterReadingCycleService
{
    public const DEFAULT_REVIEW_DAYS = 7;

    /**
     * @return array{billing_type: string, reading_day: ?int, review_days: int}
     */
    public function effectiveConfig(Building $building): array
    {
        $config = PaymentConfiguration::withoutGlobalScope('landlord')
            ->where('landlord_id', $building->landlord_id)
            ->first();

        return [
            'billing_type' => $building->water_billing_type ?? $config?->water_billing_type ?? 'none',
            'reading_day' => $building->water_reading_day ?? $config?->water_reading_day,
            'review_days' => (int) ($building->water_review_days ?? $config?->water_review_days ?? self::DEFAULT_REVIEW_DAYS),
        ];
    }

    /**
     * Consumption-billed buildings (the only ones that need readings) with an
     * assigned caretaker.
     *
     * @return Collection<int, Building>
     */
    public function consumptionBuildingsWithCaretaker(): Collection
    {
        return Building::withoutGlobalScope('landlord')
            ->whereNotNull('caretaker_id')
            ->with('caretaker:id')
            ->get()
            ->filter(fn (Building $b) => $this->effectiveConfig($b)['billing_type'] === 'consumption')
            ->values();
    }

    /**
     * @return Collection<int, Building>
     */
    public function consumptionBuildings(): Collection
    {
        return Building::withoutGlobalScope('landlord')
            ->get()
            ->filter(fn (Building $b) => $this->effectiveConfig($b)['billing_type'] === 'consumption')
            ->values();
    }
}
