<?php

declare(strict_types=1);

namespace App\Services\Water;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-91 WATER-HUB-INTELLIGENCE: turns the raw water data (Phase-86 meters,
 * -88 cadence, -89 imported history, -90 arrears, -91 production costs) into the
 * landlord's business view — consumption trends, period delta + projection,
 * per-building comparison, top consumers, leak signals (anomalies + main-vs-sub
 * non-revenue water), billing-vs-collection, and the cost-of-production margin.
 *
 * Strictly landlord-scoped (filters landlord_id). All sums are batched grouped
 * queries (no per-building / per-meter loops, no N+1) and every ratio guards
 * against a zero denominator. Soft-deleted units/buildings/invoices are excluded.
 *
 * Honesty over flattery: a metric returns null (rendered "—") when its inputs are
 * missing rather than a fabricated number — no costs logged ≠ 100% margin; an
 * unread sub-meter ≠ 100% leak; the partial current month never drives the delta
 * or projection. Money is decimal:2; consumption is whole-unit.
 */
class WaterIntelligenceService
{
    /** Months shown on the consumption-trend chart (current + 11 prior). */
    private const TREND_MONTHS = 12;

    /** Trailing months used for the "current state" window (comparison/leaks/billing/margin). */
    private const WINDOW_MONTHS = 3;

    /**
     * @return array<string, mixed>
     */
    public function forLandlord(int $landlordId): array
    {
        $trend = $this->consumptionTrend($landlordId);
        $window = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::WINDOW_MONTHS - 1)->toDateString();

        $buildings = DB::table('buildings')
            ->where('landlord_id', $landlordId)
            ->whereNull('deleted_at')
            ->pluck('name', 'id');

        $byBuilding = DB::table('water_readings')
            ->join('units', 'units.id', '=', 'water_readings.unit_id')
            ->where('water_readings.landlord_id', $landlordId)
            ->whereNull('units.deleted_at')
            ->where('water_readings.status', 'approved')
            ->where('water_readings.reading_date', '>=', $window)
            ->groupBy('units.building_id')
            ->selectRaw('units.building_id as bid, SUM(COALESCE(water_readings.consumption, 0)) as total')
            ->get();

        $windowConsumption = (float) $byBuilding->sum(fn ($r) => (float) $r->total);
        $buildingComparison = $byBuilding
            ->map(fn ($r) => ['label' => $buildings[$r->bid] ?? '#'.$r->bid, 'value' => (int) round((float) $r->total)])
            ->sortByDesc('value')->values()->all();

        $billing = $this->billingVsCollection($landlordId, $window);
        $production = $this->productionMargin($landlordId, $window, $billing['billed'], $windowConsumption);
        $anomalies = $this->anomalies($landlordId, $window);

        // Delta + projection use only COMPLETE months (drop the partial current
        // month — the last trend bucket) so early-in-month MTD can't read as a
        // crash or deflate the forecast. Projection needs >= 2 months of real
        // (non-zero) history, else it is unknown rather than a fabricated 0.
        $complete = array_slice(array_column($trend, 'value'), 0, -1);
        $cn = count($complete);
        $current = $cn >= 1 ? $complete[$cn - 1] : 0;
        $previous = $cn >= 2 ? $complete[$cn - 2] : 0;
        $deltaPct = $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;

        $recentNonZero = array_slice(array_values(array_filter($complete, fn ($v) => $v > 0)), -self::WINDOW_MONTHS);
        $projection = count($recentNonZero) >= 2 ? (int) round(array_sum($recentNonZero) / count($recentNonZero)) : null;
        $nonZero = array_filter($complete, fn ($v) => $v > 0);
        $avgMonthly = $nonZero !== [] ? (int) round(array_sum($nonZero) / count($nonZero)) : 0;

        return [
            'trend' => $trend,
            'summary' => [
                'avg_monthly_consumption' => $avgMonthly,
                'window_consumption' => (int) round($windowConsumption),
                'period_delta_pct' => $deltaPct,
                'projection_next' => $projection,
                'collection_rate_pct' => $billing['collection_rate_pct'],
                'margin' => $production['margin'],
                'margin_pct' => $production['margin_pct'],
                'costs_logged' => $production['costs_logged'],
                'anomaly_count' => count($anomalies),
            ],
            'building_comparison' => $buildingComparison,
            'top_consumers' => $this->topConsumers($landlordId, $window),
            'anomalies' => $anomalies,
            'non_revenue_water' => $this->nonRevenueWater($landlordId, $window, $buildings),
            'billing' => $billing,
            'production' => $production,
            'recent_costs' => $this->recentCosts($landlordId),
        ];
    }

    /**
     * @return list<array{label:string, value:int, period:string}>
     */
    private function consumptionTrend(int $landlordId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::TREND_MONTHS - 1);

        $rows = DB::table('water_readings')
            ->join('units', 'units.id', '=', 'water_readings.unit_id')
            ->where('water_readings.landlord_id', $landlordId)
            ->whereNull('units.deleted_at')
            ->where('water_readings.status', 'approved')
            ->where('water_readings.reading_date', '>=', $start->toDateString())
            ->selectRaw('YEAR(water_readings.reading_date) as y, MONTH(water_readings.reading_date) as m, SUM(COALESCE(water_readings.consumption, 0)) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => ((int) $r->y).'-'.((int) $r->m));

        $trend = [];
        $cursor = $start->copy();
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $key = $cursor->year.'-'.$cursor->month;
            $trend[] = [
                'label' => $cursor->format('M'),
                'value' => (int) round((float) ($rows[$key]->total ?? 0)),
                'period' => $cursor->format('Y-m'),
            ];
            $cursor->addMonth();
        }

        return $trend;
    }

    /**
     * @return list<array{unit:?string, building:?string, consumption:int}>
     */
    private function topConsumers(int $landlordId, string $window): array
    {
        return DB::table('water_readings')
            ->join('units', 'units.id', '=', 'water_readings.unit_id')
            ->leftJoin('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('water_readings.landlord_id', $landlordId)
            ->whereNull('units.deleted_at')
            ->where('water_readings.status', 'approved')
            ->where('water_readings.reading_date', '>=', $window)
            ->groupBy('water_readings.unit_id', 'units.unit_number', 'buildings.name')
            ->selectRaw('units.unit_number as unit, buildings.name as building, SUM(COALESCE(water_readings.consumption, 0)) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'unit' => $r->unit,
                'building' => $r->building,
                'consumption' => (int) round((float) $r->total),
            ])->all();
    }

    /**
     * @return list<array{unit:?string, building:?string, date:?string, consumption:int}>
     */
    private function anomalies(int $landlordId, string $window): array
    {
        return DB::table('water_readings')
            ->join('units', 'units.id', '=', 'water_readings.unit_id')
            ->leftJoin('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('water_readings.landlord_id', $landlordId)
            ->whereNull('units.deleted_at')
            ->where('water_readings.is_anomalous', true)
            ->where('water_readings.reading_date', '>=', $window)
            ->orderByDesc('water_readings.reading_date')
            ->limit(10)
            ->get(['units.unit_number as unit', 'buildings.name as building', 'water_readings.reading_date as date', 'water_readings.consumption'])
            ->map(fn ($r) => [
                'unit' => $r->unit,
                'building' => $r->building,
                'date' => $r->date,
                'consumption' => (int) round((float) $r->consumption),
            ])->all();
    }

    /**
     * Main-vs-sub reconciliation: for every TOP-LEVEL meter that feeds sub-meters,
     * the gap between the main reading and the sum of its sub-meters is non-revenue
     * water (a leak or unmetered draw). Only top-level mains are considered so a
     * mid-tier meter isn't double-counted. The loss is only trustworthy when the
     * main AND every sub were read in the window — otherwise an unread sub would
     * masquerade as 100% loss, so loss/loss_pct are withheld (complete = false)
     * and mains with no reading at all are dropped (nothing to reconcile).
     *
     * @param  \Illuminate\Support\Collection<int, string>  $buildings
     * @return list<array<string, mixed>>
     */
    private function nonRevenueWater(int $landlordId, string $window, $buildings): array
    {
        $mainMeters = DB::table('water_meters as m')
            ->where('m.landlord_id', $landlordId)
            ->whereNull('m.deleted_at')
            ->whereNull('m.parent_meter_id')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('water_meters as s')
                ->whereColumn('s.parent_meter_id', 'm.id')
                ->whereNull('s.deleted_at'))
            ->get(['m.id', 'm.building_id', 'm.serial_number']);

        if ($mainMeters->isEmpty()) {
            return [];
        }

        $mainIds = $mainMeters->pluck('id')->all();
        $subs = DB::table('water_meters')
            ->whereIn('parent_meter_id', $mainIds)
            ->whereNull('deleted_at')
            ->get(['id', 'parent_meter_id']);

        $allMeterIds = array_merge($mainIds, $subs->pluck('id')->all());
        $byMeter = DB::table('water_readings')
            ->whereIn('meter_id', $allMeterIds)
            ->where('status', 'approved')
            ->where('reading_date', '>=', $window)
            ->groupBy('meter_id')
            ->selectRaw('meter_id, SUM(COALESCE(consumption, 0)) as total, COUNT(*) as cnt')
            ->get()->keyBy('meter_id');

        $subsByParent = $subs->groupBy('parent_meter_id');

        return $mainMeters->map(function ($m) use ($byMeter, $subsByParent, $buildings) {
            return $this->reconcileMeter($m, $byMeter, $subsByParent, $buildings);
        })
            ->filter(fn ($r) => $r['main'] > 0)
            ->values()->all();
    }

    /**
     * Water billed vs collected. Payments are not water-allocated (they hit the
     * invoice total), so the water share of each payment is approximated pro-rata
     * by water_due / total_due — documented as an estimate. amount_paid is capped
     * at total_due upstream (overpayment routes to the wallet), so collected can
     * never exceed billed; the rate is clamped defensively all the same.
     *
     * @return array{billed:float, collected:float, collection_rate_pct:?float, outstanding:float}
     */
    private function billingVsCollection(int $landlordId, string $window): array
    {
        $row = DB::table('invoices')
            ->where('landlord_id', $landlordId)
            ->whereNull('voided_at')
            ->whereNull('deleted_at')
            ->where('billing_period_start', '>=', $window)
            ->where('water_due', '>', 0)
            ->selectRaw('COALESCE(SUM(water_due), 0) as billed, COALESCE(SUM(amount_paid * water_due / NULLIF(total_due, 0)), 0) as collected')
            ->first();

        $billed = round((float) ($row->billed ?? 0), 2);
        $collected = round((float) ($row->collected ?? 0), 2);

        return [
            'billed' => $billed,
            'collected' => $collected,
            'collection_rate_pct' => $billed > 0 ? min(100.0, round($collected / $billed * 100, 1)) : null,
            'outstanding' => round(max(0, $billed - $collected), 2),
        ];
    }

    /**
     * Cost-of-production vs revenue margin. Revenue is the water billed over the
     * window (accrual basis — the business model's intended take); cost is the
     * logged borehole production cost. When NO costs have been logged the margin is
     * unknown (null), NOT a flattering 100% — costs_logged distinguishes the two.
     *
     * @return array{cost:float, revenue:float, margin:?float, margin_pct:?float, cost_per_unit:?float, costs_logged:bool}
     */
    private function productionMargin(int $landlordId, string $window, float $revenue, float $consumption): array
    {
        $costs = DB::table('water_production_costs')
            ->where('landlord_id', $landlordId)
            ->where('cost_date', '>=', $window);
        $costsLogged = (clone $costs)->exists();
        $cost = round((float) $costs->sum('amount'), 2);

        if (! $costsLogged) {
            return [
                'cost' => 0.0,
                'revenue' => round($revenue, 2),
                'margin' => null,
                'margin_pct' => null,
                'cost_per_unit' => null,
                'costs_logged' => false,
            ];
        }

        $margin = round($revenue - $cost, 2);

        return [
            'cost' => $cost,
            'revenue' => round($revenue, 2),
            'margin' => $margin,
            'margin_pct' => $revenue > 0 ? round($margin / $revenue * 100, 1) : null,
            'cost_per_unit' => $consumption > 0 ? round($cost / $consumption, 2) : null,
            'costs_logged' => true,
        ];
    }

    /**
     * Accumulate sub-meter consumption for a main meter within the reading window.
     *
     * Returns [sub_total, subs_read_count] so the caller can determine completeness.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $subList
     * @param  \Illuminate\Support\Collection<int|string, object>  $byMeter
     * @return array{0: float, 1: int}
     */
    private function accumulateSubMeters($subList, $byMeter): array
    {
        $sub = 0.0;
        $subsRead = 0;
        foreach ($subList as $s) {
            $row = $byMeter[$s->id] ?? null;
            if ($row !== null && (int) $row->cnt > 0) {
                $subsRead++;
                $sub += (float) $row->total;
            }
        }

        return [$sub, $subsRead];
    }

    /**
     * Build the non-revenue-water reconciliation entry for a single main meter.
     *
     * @param  \Illuminate\Support\Collection<int|string, object>  $byMeter
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, object>>  $subsByParent
     * @param  \Illuminate\Support\Collection<int, string>  $buildings
     * @return array<string, mixed>
     */
    private function reconcileMeter(object $m, $byMeter, $subsByParent, $buildings): array
    {
        $mainRow = $byMeter[$m->id] ?? null;
        $main = (float) ($mainRow->total ?? 0);
        $mainHasReading = $mainRow !== null && (int) $mainRow->cnt > 0;

        $subList = $subsByParent[$m->id] ?? collect();
        $subCount = $subList->count();
        [$sub, $subsRead] = $this->accumulateSubMeters($subList, $byMeter);

        $complete = $mainHasReading && $subCount > 0 && $subsRead === $subCount;

        return array_merge(
            [
                'meter' => $m->serial_number ?: '#'.$m->id,
                'building' => $buildings[$m->building_id] ?? null,
                'main' => (int) round($main),
                'sub' => (int) round($sub),
                'complete' => $complete,
            ],
            $this->lossFields($main, $sub, $complete)
        );
    }

    /**
     * Compute the loss and loss_pct fields, withheld when the reading set is incomplete.
     *
     * @return array{loss: ?int, loss_pct: ?float}
     */
    private function lossFields(float $main, float $sub, bool $complete): array
    {
        if (! $complete) {
            return ['loss' => null, 'loss_pct' => null];
        }

        $loss = $main - $sub;

        return [
            'loss' => (int) round($loss),
            'loss_pct' => $main > 0 ? round($loss / $main * 100, 1) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentCosts(int $landlordId): array
    {
        return DB::table('water_production_costs as c')
            ->leftJoin('buildings', 'buildings.id', '=', 'c.building_id')
            ->where('c.landlord_id', $landlordId)
            ->orderByDesc('c.cost_date')
            ->orderByDesc('c.id')
            ->limit(12)
            ->get(['c.id', 'c.cost_date as date', 'c.amount', 'c.category', 'c.note', 'buildings.name as building'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->date,
                'amount' => round((float) $r->amount, 2),
                'category' => $r->category,
                'note' => $r->note,
                'building' => $r->building,
            ])->all();
    }
}
