<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-27 BI-NOI-1/2/3: Net Operating Income + cap rate analytics.
 *
 * NOI = rental income − operating expenses, per property + portfolio.
 * Cap rate = annualised NOI / estimated_value, per property.
 *
 * Expense allocation: an expense can be 'direct' (attached to a
 * specific property_id), or allocated across all properties via one
 * of three methods: per_unit, per_revenue, per_floor_area. The
 * allocation logic lives here so the UI surface (Reports/Noi.vue)
 * can render allocated + direct subtotals independently.
 */
class NoiService
{
    /**
     * NOI per property + the portfolio-level total.
     *
     * @return array{
     *   period: array{start: string, end: string},
     *   properties: list<array{property_id: int, name: string, revenue: float, direct_expenses: float, allocated_expenses: float, noi: float, noi_margin: float|null}>,
     *   portfolio: array{revenue: float, direct_expenses: float, allocated_expenses: float, noi: float, noi_margin: float|null}
     * }
     */
    public function byProperty(int $landlordId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $start = $start ?? Carbon::now()->subYear()->startOfDay();
        $end = $end ?? Carbon::now()->endOfDay();

        // Aggregate unit counts in one query to avoid lazy-loading the
        // HasManyThrough relation per property.
        $unitCountPerProperty = collect(
            DB::table('units')
                ->join('buildings', 'buildings.id', '=', 'units.building_id')
                ->where('buildings.landlord_id', $landlordId)
                ->whereNull('units.deleted_at')
                ->whereNull('buildings.deleted_at')
                ->select('buildings.property_id', DB::raw('COUNT(units.id) as unit_count'))
                ->groupBy('buildings.property_id')
                ->pluck('unit_count', 'buildings.property_id'),
        );

        $properties = Property::query()
            ->where('landlord_id', $landlordId)
            ->get();

        $revenuePerProperty = $this->revenuePerProperty($landlordId, $start, $end);

        $directExpenses = $this->directExpensesPerProperty($landlordId, $start, $end);
        $allocatedExpenses = $this->allocatedExpensesPerProperty(
            $landlordId,
            $start,
            $end,
            $revenuePerProperty,
            $unitCountPerProperty,
        );

        $rows = $properties->map(function ($property) use ($revenuePerProperty, $directExpenses, $allocatedExpenses) {
            $revenue = (float) ($revenuePerProperty[$property->id] ?? 0);
            $direct = (float) ($directExpenses[$property->id] ?? 0);
            $allocated = (float) ($allocatedExpenses[$property->id] ?? 0);
            $noi = $revenue - $direct - $allocated;

            return [
                'property_id' => $property->id,
                'name' => $property->name,
                'revenue' => round($revenue, 2),
                'direct_expenses' => round($direct, 2),
                'allocated_expenses' => round($allocated, 2),
                'noi' => round($noi, 2),
                'noi_margin' => $revenue > 0 ? round($noi / $revenue, 4) : null,
            ];
        })->values()->all();

        $totalRevenue = array_sum(array_column($rows, 'revenue'));
        $totalDirect = array_sum(array_column($rows, 'direct_expenses'));
        $totalAllocated = array_sum(array_column($rows, 'allocated_expenses'));
        $totalNoi = $totalRevenue - $totalDirect - $totalAllocated;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'properties' => $rows,
            'portfolio' => [
                'revenue' => round($totalRevenue, 2),
                'direct_expenses' => round($totalDirect, 2),
                'allocated_expenses' => round($totalAllocated, 2),
                'noi' => round($totalNoi, 2),
                'noi_margin' => $totalRevenue > 0 ? round($totalNoi / $totalRevenue, 4) : null,
            ],
        ];
    }

    /**
     * Cap rate per property = (annualised NOI) / estimated_value.
     *
     * Annualisation: NOI is normalised to a full year by scaling by
     * (365 / days_in_period). A 3-month NOI multiplies by 4×.
     * Properties without an estimated_value return cap_rate=null.
     *
     * @return list<array{property_id: int, name: string, annualised_noi: float, estimated_value: float|null, cap_rate: float|null, band: string}>
     */
    public function capRate(int $landlordId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $start = $start ?? Carbon::now()->subYear()->startOfDay();
        $end = $end ?? Carbon::now()->endOfDay();

        $noi = $this->byProperty($landlordId, $start, $end);
        $periodDays = max(1, $start->diffInDays($end) + 1);
        $annualisation = 365.0 / $periodDays;

        // estimated_value lookup. The byProperty path doesn't carry
        // it (deliberately — it's a cap-rate-only concern).
        $properties = Property::query()
            ->where('landlord_id', $landlordId)
            ->select('id', 'name', 'estimated_value')
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($noi['properties'] as $row) {
            $property = $properties->get($row['property_id']);
            $value = $property?->estimated_value !== null ? (float) $property->estimated_value : null;
            $annualisedNoi = round($row['noi'] * $annualisation, 2);

            $capRate = $value !== null && $value > 0
                ? round($annualisedNoi / $value, 4)
                : null;

            $rows[] = [
                'property_id' => $row['property_id'],
                'name' => $row['name'],
                'annualised_noi' => $annualisedNoi,
                'estimated_value' => $value,
                'cap_rate' => $capRate,
                'band' => $this->capRateBand($capRate),
            ];
        }

        return $rows;
    }

    /**
     * Cap rate band per Kenyan residential market convention:
     *   <6%  amber (underperforming or overvalued)
     *   6-9% green (typical residential)
     *   >9%  emerald (commercial / high-yield)
     *   null gray  (no value declared)
     */
    private function capRateBand(?float $rate): string
    {
        if ($rate === null) {
            return 'unknown';
        }
        if ($rate < 0.06) {
            return 'amber';
        }
        if ($rate <= 0.09) {
            return 'green';
        }

        return 'emerald';
    }

    /**
     * Revenue per property = sum of payments whose lease.unit.building.property
     * belongs to that property, in the window.
     *
     * @return array<int, float>
     */
    private function revenuePerProperty(int $landlordId, Carbon $start, Carbon $end): array
    {
        $rows = Payment::query()
            ->join('leases', 'leases.id', '=', 'payments.lease_id')
            ->join('units', 'units.id', '=', 'leases.unit_id')
            ->join('buildings', 'buildings.id', '=', 'units.building_id')
            ->where('payments.landlord_id', $landlordId)
            ->where(function ($q) {
                $q->whereNull('payments.is_voided')->orWhere('payments.is_voided', false);
            })
            ->whereBetween('payments.payment_date', [$start, $end])
            ->select('buildings.property_id as property_id', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('buildings.property_id')
            ->pluck('total', 'property_id');

        return $rows->all();
    }

    /**
     * Direct expenses per property = expenses with allocation_method=direct
     * AND property_id set. Building-level + unit-level expenses bubble up
     * to their property too.
     *
     * @return array<int, float>
     */
    private function directExpensesPerProperty(int $landlordId, Carbon $start, Carbon $end): array
    {
        // Property-level + building-level + unit-level direct expenses.
        // Use COALESCE through join chain so building/unit roots map to
        // the right property.
        $rows = Expense::query()
            ->leftJoin('buildings', 'buildings.id', '=', 'expenses.building_id')
            ->leftJoin('units', 'units.id', '=', 'expenses.unit_id')
            ->leftJoin('buildings as unit_buildings', 'unit_buildings.id', '=', 'units.building_id')
            ->where('expenses.landlord_id', $landlordId)
            ->where(function ($q) {
                $q->where('expenses.allocation_method', 'direct')
                    ->orWhereNull('expenses.allocation_method');
            })
            ->whereBetween('expenses.expense_date', [$start, $end])
            ->select(
                DB::raw('COALESCE(expenses.property_id, buildings.property_id, unit_buildings.property_id) as property_id'),
                DB::raw('SUM(expenses.amount) as total'),
            )
            ->groupBy('property_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            if ($row->property_id === null) {
                continue; // truly unattributed; falls into portfolio-only via the math
            }
            $out[(int) $row->property_id] = (float) $row->total;
        }

        return $out;
    }

    /**
     * Allocated expenses per property — for expenses with
     * allocation_method ∈ {per_unit, per_revenue, per_floor_area}.
     * The per_floor_area method requires units.floor_area_m2 which
     * doesn't exist yet (documented in the PRD); we treat it as
     * per_unit for now to avoid silent zeros.
     *
     * @param  array<int, float>  $revenuePerProperty
     * @param  \Illuminate\Support\Collection<int, int>  $unitCountPerProperty
     * @return array<int, float>
     */
    private function allocatedExpensesPerProperty(
        int $landlordId,
        Carbon $start,
        Carbon $end,
        array $revenuePerProperty,
        $unitCountPerProperty,
    ): array {
        $allocatable = Expense::query()
            ->where('landlord_id', $landlordId)
            ->whereIn('allocation_method', ['per_unit', 'per_revenue', 'per_floor_area'])
            ->whereBetween('expense_date', [$start, $end])
            ->select('id', 'amount', 'allocation_method')
            ->get();

        if ($allocatable->isEmpty()) {
            return [];
        }

        $totalUnits = max(1, (int) $unitCountPerProperty->sum());
        $totalRevenue = max(0.01, array_sum($revenuePerProperty));

        $out = [];
        foreach ($allocatable as $expense) {
            $method = $expense->allocation_method;
            // per_floor_area falls back to per_unit until floor area
            // lands on the units table.
            if ($method === 'per_floor_area') {
                $method = 'per_unit';
            }

            foreach ($unitCountPerProperty as $propertyId => $units) {
                $share = 0.0;
                if ($method === 'per_unit') {
                    $share = (float) $expense->amount * ($units / $totalUnits);
                } elseif ($method === 'per_revenue') {
                    $propertyRevenue = $revenuePerProperty[$propertyId] ?? 0.0;
                    $share = (float) $expense->amount * ($propertyRevenue / $totalRevenue);
                }
                $out[$propertyId] = ($out[$propertyId] ?? 0.0) + $share;
            }
        }

        return $out;
    }
}
