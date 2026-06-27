<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-27 BI-FORECAST-1/2/3: rent-roll forecasting + seasonality
 * adjustment + vacancy projection.
 *
 * Three views of forward revenue:
 *   1. rentRoll(landlordId, monthsAhead) — expected_revenue per month
 *      with low/high estimates. Low = active leases × historical
 *      collection rate. High = active leases + vacant units × avg
 *      rent × historical vacancy-fill rate.
 *   2. seasonalityFactor(landlordId, month) — landlord-specific
 *      seasonal multiplier from the last 3 years of payment history.
 *      Falls back to 1.0 when there's <12 months of data.
 *   3. vacancyProjection(landlordId) — vacant units with
 *      expected_fill_date and lost_revenue_kes from the landlord's
 *      own mean-time-to-fill.
 *
 * Methodology details + edge cases are in docs/runbooks/bi.md.
 */
class ForecastService
{
    /**
     * Rent-roll forecast for the next N months.
     *
     * For each month from now+1 to now+N:
     *   - active_rent       = Σ rent_amount for leases active that month
     *   - expected_revenue  = active_rent × collection_rate × seasonality_factor
     *   - low_estimate      = active_rent × collection_rate (conservative)
     *   - high_estimate     = active_rent + (vacant_units × avg_rent × fill_rate)
     *
     * Leases ending mid-window drop off at their end_date month.
     *
     * @return array{
     *   collection_rate: float,
     *   vacancy_fill_rate: float,
     *   months: list<array{month: string, active_rent: float, expected_revenue: float, low_estimate: float, high_estimate: float, seasonality: float}>,
     * }
     */
    public function rentRoll(int $landlordId, int $monthsAhead = 12): array
    {
        $monthsAhead = max(1, min(24, $monthsAhead));
        $collectionRate = $this->collectionRate($landlordId);
        $fillRate = $this->vacancyFillRate($landlordId);
        $vacantCount = $this->vacantUnitCount($landlordId);
        $avgRent = $this->averageRent($landlordId);

        $activeLeases = Lease::query()
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->select('id', 'rent_amount', 'start_date', 'end_date')
            ->get();

        $rows = [];
        $start = Carbon::now()->addMonth()->startOfMonth();

        for ($i = 0; $i < $monthsAhead; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $monthKey = $monthStart->format('Y-m');

            $activeRent = $activeLeases->filter(function ($lease) use ($monthStart, $monthEnd) {
                $startsBeforeMonthEnd = $lease->start_date->lessThanOrEqualTo($monthEnd);
                $endsAfterMonthStart = $lease->end_date === null
                    || $lease->end_date->copy()->endOfDay()->greaterThanOrEqualTo($monthStart);

                return $startsBeforeMonthEnd && $endsAfterMonthStart;
            })->sum('rent_amount');

            $seasonality = $this->seasonalityFactor($landlordId, (int) $monthStart->month);
            $low = round((float) $activeRent * $collectionRate, 2);
            $expected = round((float) $activeRent * $collectionRate * $seasonality, 2);
            $high = round((float) $activeRent + ($vacantCount * $avgRent * $fillRate), 2);

            $rows[] = [
                'month' => $monthKey,
                'active_rent' => round((float) $activeRent, 2),
                'expected_revenue' => $expected,
                'low_estimate' => $low,
                'high_estimate' => $high,
                'seasonality' => round($seasonality, 3),
            ];
        }

        return [
            'collection_rate' => round($collectionRate, 4),
            'vacancy_fill_rate' => round($fillRate, 4),
            'vacant_unit_count' => $vacantCount,
            'average_rent' => round($avgRent, 2),
            'months' => $rows,
        ];
    }

    /**
     * Per-month seasonality factor derived from this landlord's last
     * 3 years of payment history. Returns 1.0 when <12 months of data
     * is available — naive forecasting is better than guessing
     * seasonality from a thin sample.
     */
    public function seasonalityFactor(int $landlordId, int $month): float
    {
        if ($month < 1 || $month > 12) {
            return 1.0;
        }

        $monthly = $this->fetchMonthlyPaymentTotals($landlordId);

        if ($monthly->count() < 12) {
            return 1.0;
        }

        $annualMean = $monthly->avg('total');
        if (! $this->isValidMean($annualMean)) {
            return 1.0;
        }

        $forMonth = $monthly->where('m', $month);
        if ($forMonth->isEmpty()) {
            return 1.0;
        }

        return (float) $forMonth->avg('total') / (float) $annualMean;
    }

    /**
     * Fetch per-year/month payment totals for the landlord over the
     * last 3 years, excluding voided payments.
     */
    private function fetchMonthlyPaymentTotals(int $landlordId): \Illuminate\Support\Collection
    {
        $lookbackStart = Carbon::now()->subYears(3)->startOfDay();
        $lookbackEnd = Carbon::now()->subDay();

        return Payment::query()
            ->where('landlord_id', $landlordId)
            ->where(function ($q) {
                $q->whereNull('is_voided')->orWhere('is_voided', false);
            })
            ->whereBetween('payment_date', [$lookbackStart, $lookbackEnd])
            ->select(
                DB::raw('YEAR(payment_date) as y'),
                DB::raw('MONTH(payment_date) as m'),
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('y', 'm')
            ->get();
    }

    /**
     * Returns true when the mean value is non-null and non-zero,
     * making it safe to use as a divisor in the seasonality ratio.
     */
    private function isValidMean(mixed $mean): bool
    {
        return $mean !== null && (float) $mean !== 0.0;
    }

    /**
     * Vacant unit projection — each vacant unit with the expected
     * fill date (today + landlord's mean time-to-fill) and the lost
     * revenue between today and the fill date.
     *
     * @return list<array{unit_id: int, unit_number: string, vacant_since: string|null, expected_fill_date: string, lost_revenue_kes: float}>
     */
    public function vacancyProjection(int $landlordId): array
    {
        $meanFillDays = $this->meanTimeToFillDays($landlordId);

        $vacantUnits = Unit::query()
            ->where('landlord_id', $landlordId)
            ->where('status', 'vacant')
            ->with('leases')
            ->get();

        $today = Carbon::now()->startOfDay();
        $rows = [];

        foreach ($vacantUnits as $unit) {
            // vacant_since = the last lease's end_date (if any).
            $lastEnded = $unit->leases->where('end_date', '!=', null)->sortByDesc('end_date')->first();
            $vacantSince = $lastEnded?->end_date;
            $vacantSinceStr = $vacantSince?->toDateString();

            $expectedFill = $today->copy()->addDays((int) round($meanFillDays));
            $daysVacantToFill = $today->diffInDays($expectedFill);
            $monthlyRent = (float) ($unit->target_rent ?? 0);
            $lostRevenue = round(($monthlyRent / 30.0) * $daysVacantToFill, 2);

            $rows[] = [
                'unit_id' => $unit->id,
                'unit_number' => (string) $unit->unit_number,
                'vacant_since' => $vacantSinceStr,
                'expected_fill_date' => $expectedFill->toDateString(),
                'lost_revenue_kes' => $lostRevenue,
            ];
        }

        // Highest lost revenue first.
        usort($rows, fn ($a, $b) => $b['lost_revenue_kes'] <=> $a['lost_revenue_kes']);

        return $rows;
    }

    /**
     * Historical collection rate: total payments received ÷ total
     * rent expected, over the last 12 months. Falls back to 0.85
     * (a conservative Kenyan-market default) when there isn't enough
     * history for a meaningful ratio.
     */
    private function collectionRate(int $landlordId): float
    {
        $start = Carbon::now()->subYear()->startOfMonth();
        $end = Carbon::now()->subDay();

        $expected = Lease::query()
            ->where('landlord_id', $landlordId)
            ->whereBetween('start_date', [$start->copy()->subYears(2), $end])
            ->sum(DB::raw('rent_amount * 12'));

        $collected = Payment::query()
            ->where('landlord_id', $landlordId)
            ->where(function ($q) {
                $q->whereNull('is_voided')->orWhere('is_voided', false);
            })
            ->whereBetween('payment_date', [$start, $end])
            ->sum('amount');

        if ((float) $expected === 0.0) {
            return 0.85;
        }
        $rate = (float) $collected / (float) $expected;

        // Clamp to a sane range — outliers from new portfolios can
        // produce >1.0 (lease pro-ration) or near-zero (no historical
        // expectations matched).
        return max(0.5, min(1.0, $rate ?: 0.85));
    }

    /**
     * Fraction of vacant units that get filled within a forecast
     * month, based on the landlord's history. Conservative default
     * is 0.4 when there's no history.
     */
    private function vacancyFillRate(int $landlordId): float
    {
        $cutoff = Carbon::now()->subYear();

        $newLeases = Lease::query()
            ->where('landlord_id', $landlordId)
            ->where('start_date', '>=', $cutoff)
            ->count();

        $totalUnits = Unit::query()
            ->where('landlord_id', $landlordId)
            ->count();

        if ($totalUnits === 0) {
            return 0.4;
        }

        // Mean leases per unit per year → fraction filled per month.
        $perMonth = ($newLeases / max(1, $totalUnits)) / 12.0;

        return max(0.1, min(0.9, $perMonth ?: 0.4));
    }

    private function vacantUnitCount(int $landlordId): int
    {
        return Unit::query()
            ->where('landlord_id', $landlordId)
            ->where('status', 'vacant')
            ->count();
    }

    private function averageRent(int $landlordId): float
    {
        $avg = Unit::query()
            ->where('landlord_id', $landlordId)
            ->whereNotNull('target_rent')
            ->avg('target_rent');

        return (float) ($avg ?? 0);
    }

    private function meanTimeToFillDays(int $landlordId): float
    {
        // For each lease in the last 12 months, find the prior lease on
        // the same unit. days_between = current.start_date - prior.end_date.
        $rows = DB::table('leases as l1')
            ->join('leases as l2', function ($join) {
                $join->on('l2.unit_id', '=', 'l1.unit_id')
                    ->whereColumn('l2.start_date', '>', 'l1.end_date');
            })
            ->where('l1.landlord_id', $landlordId)
            ->whereNotNull('l1.end_date')
            ->whereBetween('l2.start_date', [Carbon::now()->subYear(), Carbon::now()])
            ->select(DB::raw('AVG(DATEDIFF(l2.start_date, l1.end_date)) as mean_days'))
            ->value('mean_days');

        if ($rows === null) {
            return 45.0; // Conservative default — 1.5 months.
        }

        return max(7.0, min(180.0, (float) $rows));
    }
}
