<?php

declare(strict_types=1);

namespace App\Services\Water;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-93 WATER-TENANT-SELFSERVICE: a single water account's self-service view —
 * the consumption history, a usage summary, a high-usage/leak self-alert (the
 * Phase-86 is_anomalous spike flag), the water-charge history, and the meter's
 * disconnection state.
 *
 * Deliberately UNIT-centric (charges by lease) so the Phase-94+ water client
 * dashboard reuses it verbatim — a water client is a unit/water-line without the
 * tenancy. Reading queries are bounded by the occupancy window ($since/$until)
 * because water_readings has no lease_id: without the floor a new tenant would
 * see the PREVIOUS occupant's history (reviewer CRITICAL). DB::table (scope-free)
 * is safe — the caller passes the unit/lease it already resolved + owns.
 */
class WaterAccountService
{
    private const HISTORY_MONTHS = 12;

    /** @var array<string, list<array{label:string, value:int, period:string}>> */
    private array $historyCache = [];

    /** @var array<string, object|null> */
    private array $latestCache = [];

    /**
     * @return array<string, mixed>
     */
    public function overview(int $unitId, ?int $leaseId = null, ?string $since = null, ?string $until = null): array
    {
        return [
            'history' => $this->consumptionHistory($unitId, $since, $until),
            'summary' => $this->summary($unitId, $since, $until),
            'alert' => $this->latestAnomaly($unitId, $since, $until),
            'charges' => $leaseId !== null ? $this->chargeHistory($leaseId) : [],
            'disconnection' => $this->disconnection($unitId),
        ];
    }

    /**
     * @return list<array{label:string, value:int, period:string}>
     */
    public function consumptionHistory(int $unitId, ?string $since = null, ?string $until = null): array
    {
        $cacheKey = $this->key($unitId, $since, $until);
        if (isset($this->historyCache[$cacheKey])) {
            return $this->historyCache[$cacheKey];
        }

        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS - 1);

        $rows = $this->boundedReadings($unitId, $since, $until)
            ->where('reading_date', '>=', $start->toDateString())
            ->selectRaw('YEAR(reading_date) as y, MONTH(reading_date) as m, SUM(COALESCE(consumption, 0)) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => ((int) $r->y).'-'.((int) $r->m));

        $history = [];
        $cursor = $start->copy();
        for ($i = 0; $i < self::HISTORY_MONTHS; $i++) {
            $history[] = [
                'label' => $cursor->format('M'),
                'value' => (int) round((float) ($rows[$cursor->year.'-'.$cursor->month]->total ?? 0)),
                'period' => $cursor->format('Y-m'),
            ];
            $cursor->addMonth();
        }

        return $this->historyCache[$cacheKey] = $history;
    }

    /**
     * @return array{latest_consumption:?int, latest_date:?string, avg_monthly:int, ytd_consumption:int}
     */
    public function summary(int $unitId, ?string $since = null, ?string $until = null): array
    {
        $latest = $this->latestReading($unitId, $since, $until);

        $values = array_column($this->consumptionHistory($unitId, $since, $until), 'value');
        $nonZero = array_filter($values, fn ($v) => $v > 0);
        $avgMonthly = $nonZero !== [] ? (int) round(array_sum($nonZero) / count($nonZero)) : 0;

        // Year-to-date, but never before the occupancy window started.
        $ytdFloor = Carbon::now()->startOfYear()->toDateString();
        if ($since !== null && $since > $ytdFloor) {
            $ytdFloor = $since;
        }
        $ytd = (float) $this->boundedReadings($unitId, null, $until)
            ->where('reading_date', '>=', $ytdFloor)
            ->sum('consumption');

        return [
            'latest_consumption' => $latest !== null ? (int) round((float) $latest->total) : null,
            'latest_date' => $latest !== null ? Carbon::parse($latest->reading_date)->toDateString() : null,
            'avg_monthly' => $avgMonthly,
            'ytd_consumption' => (int) round($ytd),
        ];
    }

    /**
     * The latest approved reading flagged as a usage spike (Phase-86) — the leak
     * self-alert. Null when the latest reading is normal. (Imported historical
     * readings are not spike-flagged; the alert reflects normally-recorded ones.)
     *
     * @return array{consumption:int, reading_date:?string}|null
     */
    public function latestAnomaly(int $unitId, ?string $since = null, ?string $until = null): ?array
    {
        $latest = $this->latestReading($unitId, $since, $until);

        if ($latest === null || ! (bool) $latest->is_anomalous) {
            return null;
        }

        return [
            'consumption' => (int) round((float) $latest->total),
            'reading_date' => Carbon::parse($latest->reading_date)->toDateString(),
        ];
    }

    /**
     * @return list<array{period:?string, water_due:float, paid:bool, status:string}>
     */
    public function chargeHistory(int $leaseId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS - 1)->toDateString();

        return DB::table('invoices')
            ->where('lease_id', $leaseId)
            ->whereNull('voided_at')
            ->whereNull('deleted_at')
            ->where('water_due', '>', 0)
            ->where('billing_period_start', '>=', $start)
            ->orderByDesc('billing_period_start')
            ->get(['billing_period_start', 'water_due', 'total_due', 'amount_paid', 'status'])
            ->map(fn ($inv) => [
                'period' => $inv->billing_period_start ? Carbon::parse($inv->billing_period_start)->format('Y-m') : null,
                'water_due' => round((float) $inv->water_due, 2),
                // Whole-invoice settlement (water isn't payment-separable) — the UI
                // labels this at invoice level, not as a water-line status.
                'paid' => (float) $inv->amount_paid >= (float) $inv->total_due,
                'status' => (string) $inv->status,
            ])->all();
    }

    /**
     * The unit meter's service-disconnection state (Phase-90). Folded into the
     * service so the Phase-94+ water-client view ships it from the same surface.
     *
     * @return array{disconnected:bool, reason:?string}
     */
    public function disconnection(int $unitId): array
    {
        $meter = DB::table('water_meters')
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first(['disconnected_at', 'disconnect_reason']);

        return [
            'disconnected' => $meter !== null && $meter->disconnected_at !== null,
            'reason' => $meter->disconnect_reason ?? null,
        ];
    }

    private function latestReading(int $unitId, ?string $since, ?string $until): ?object
    {
        $cacheKey = $this->key($unitId, $since, $until);
        if (array_key_exists($cacheKey, $this->latestCache)) {
            return $this->latestCache[$cacheKey];
        }

        return $this->latestCache[$cacheKey] = $this->boundedReadings($unitId, $since, $until)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->selectRaw('COALESCE(consumption, 0) as total, reading_date, is_anomalous')
            ->first();
    }

    /**
     * Approved readings for the unit, bounded to the occupancy window so one
     * account never sees another's history on the same physical unit.
     */
    private function boundedReadings(int $unitId, ?string $since, ?string $until): \Illuminate\Database\Query\Builder
    {
        return DB::table('water_readings')
            ->where('unit_id', $unitId)
            ->where('status', 'approved')
            ->when($since !== null, fn ($q) => $q->where('reading_date', '>=', $since))
            ->when($until !== null, fn ($q) => $q->where('reading_date', '<=', $until));
    }

    private function key(int $unitId, ?string $since, ?string $until): string
    {
        return $unitId.'|'.($since ?? '').'|'.($until ?? '');
    }
}
