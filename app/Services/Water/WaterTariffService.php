<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Models\Unit;
use App\Models\WaterReading;
use App\Services\WaterRateService;

/**
 * Phase-87 WATER-TARIFF-ENGINE: turns consumption into a charge with real
 * water-tariff depth — tiered/block rates, a standing charge, sewerage % and
 * VAT %, and a minimum bill. NON-DESTRUCTIVE: when none of those are configured
 * the result equals the old flat `rate * consumption` / `flat_water_rate`, so
 * the live biller is unchanged until a landlord opts in.
 *
 * Config resolves per-field building-override -> landlord PaymentConfiguration
 * -> default, mirroring WaterRateService (which still owns the flat unit rate).
 */
class WaterTariffService
{
    public function __construct(private WaterRateService $rateService) {}

    /**
     * Variable (consumption) charge for one reading's worth of usage.
     */
    public function consumptionChargeForUnit(Unit $unit, float $consumption): float
    {
        return $this->computeConsumptionCharge($consumption, $this->resolveForUnit($unit));
    }

    public function costForReading(WaterReading $reading): float
    {
        if ($reading->consumption <= 0) {
            return 0.0;
        }

        $unit = $reading->unit;
        if (! $unit) {
            return round((float) $reading->consumption * $this->rateService->getDefaultRate(), 2);
        }

        return $this->consumptionChargeForUnit($unit, (float) $reading->consumption);
    }

    /**
     * @param  array<string, mixed>  $tariff
     */
    public function computeConsumptionCharge(float $consumption, array $tariff): float
    {
        if ($consumption <= 0) {
            return 0.0;
        }

        $bands = $tariff['tiered_tariffs'] ?? null;
        if (is_array($bands) && count($bands) > 0) {
            return $this->applyTiers($consumption, $bands);
        }

        return round($consumption * (float) ($tariff['unit_rate'] ?? 0), 2);
    }

    /**
     * Apply the per-period fixed components on top of a base water charge:
     * + standing charge, + sewerage %, + VAT %, floored at the minimum bill.
     *
     * @param  array<string, mixed>  $tariff
     */
    public function assembleWaterCharge(float $base, array $tariff): float
    {
        $standing = (float) ($tariff['standing_charge'] ?? 0);
        $seweragePct = (float) ($tariff['sewerage_percent'] ?? 0);
        $vatPct = (float) ($tariff['vat_percent'] ?? 0);
        $minimum = (float) ($tariff['minimum_charge'] ?? 0);

        $subtotal = $base + $standing;
        $sewerage = $subtotal * $seweragePct / 100;
        $vat = ($subtotal + $sewerage) * $vatPct / 100;
        $total = $subtotal + $sewerage + $vat;

        return round(max($total, $minimum), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveForUnit(Unit $unit): array
    {
        $building = $unit->building;
        $tariff = $this->resolveForBuilding($building);
        // Unit-aware flat rate keeps WaterRateService's 3-tier resolution intact.
        $tariff['unit_rate'] = $this->rateService->getEffectiveRate($unit);

        return $tariff;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveForBuilding(?Building $building): array
    {
        $config = $building
            ? PaymentConfiguration::where('landlord_id', $building->landlord_id)->first()
            : null;

        $pick = fn (string $field) => $building?->{$field} ?? $config?->{$field};

        return [
            'unit_rate' => $building
                ? $this->rateService->getEffectiveRateForBuilding($building)
                : $this->rateService->getDefaultRate(),
            'tiered_tariffs' => $building?->tiered_tariffs ?? $config?->tiered_tariffs,
            'standing_charge' => (float) ($pick('water_standing_charge') ?? 0),
            'minimum_charge' => (float) ($pick('water_minimum_charge') ?? 0),
            'sewerage_percent' => (float) ($pick('water_sewerage_percent') ?? 0),
            'vat_percent' => (float) ($pick('water_vat_percent') ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private function applyTiers(float $consumption, array $bands): float
    {
        usort($bands, fn ($a, $b) => ($a['from'] ?? 0) <=> ($b['from'] ?? 0));

        $charge = 0.0;
        foreach ($bands as $band) {
            $from = (float) ($band['from'] ?? 0);
            $hasTo = isset($band['to']) && $band['to'] !== null && $band['to'] !== '';
            $upper = $hasTo ? min($consumption, (float) $band['to']) : $consumption;
            $units = max(0.0, $upper - $from);
            $charge += $units * (float) ($band['rate'] ?? 0);
        }

        return round($charge, 2);
    }
}
