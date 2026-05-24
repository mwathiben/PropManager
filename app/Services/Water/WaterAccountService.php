<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\WaterConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase-93 WATER-TENANT-SELFSERVICE: a single water account's self-service view —
 * the consumption history, a usage summary, a high-usage/leak self-alert (the
 * Phase-86 is_anomalous spike flag), the water-charge history, and the meter's
 * disconnection state.
 *
 * Two account shapes feed the SAME return contract (so the shared Components/Water/*
 * render either verbatim):
 *  - UNIT-centric (tenant, Phase 93): readings scoped by unit_id, charges by lease.
 *  - CONNECTION/METER-centric (water client, Phase 96): readings scoped by the
 *    connection's meter_id, charges deferred to Phase 97 (none exist yet).
 *
 * Reading queries are bounded by a service window ($since/$until) because
 * water_readings has no lease/connection id: without the floor a re-let unit or a
 * re-used meter would surface the PREVIOUS occupant's history (Phase-93 reviewer
 * CRITICAL). DB::table (scope-free) is safe — the caller passes the unit/meter it
 * already resolved + owns.
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
            'history' => $this->historyFor('unit_id', $unitId, $since, $until),
            'summary' => $this->summaryFor('unit_id', $unitId, $since, $until),
            'alert' => $this->anomalyFor('unit_id', $unitId, $since, $until),
            'charges' => $leaseId !== null ? $this->chargeHistory($leaseId) : [],
            'disconnection' => $this->disconnection($unitId),
        ];
    }

    /**
     * Phase-96 WATER-CLIENT-DASHBOARD: the same overview for a water client's line.
     * The connection IS the account — readings come from its meter, bounded to when
     * the line was connected (so a meter that previously served a tenant doesn't
     * leak that history). Phase-98: charges come from the connection's invoices and
     * are shown regardless of meter (a flat-rate line bills with no meter).
     *
     * @return array<string, mixed>
     */
    public function overviewForConnection(WaterConnection $connection): array
    {
        // Charges are meter-independent — a flat-rate line still bills.
        $charges = $this->chargeHistoryForConnection($connection);

        // Resolve the meter through the (soft-delete- and tenant-scoped) relation,
        // NOT the raw meter_id: a decommissioned or foreign meter must never drive
        // the account. Belt-and-suspenders, every read below is also bounded by
        // landlord_id — water_readings has no connection id, so (as in Phase 93) an
        // unbounded query could otherwise surface another account's history.
        $meter = $connection->meter;
        if ($meter === null) {
            // A flat-rate, not-yet-metered, or decommissioned line has no consumption.
            return $this->emptyAccount($charges);
        }

        $since = ($connection->connected_at ?? $connection->created_at)?->toDateString();
        $landlordId = $connection->landlord_id;

        return [
            'history' => $this->historyFor('meter_id', $meter->id, $since, null, $landlordId),
            'summary' => $this->summaryFor('meter_id', $meter->id, $since, null, $landlordId),
            'alert' => $this->anomalyFor('meter_id', $meter->id, $since, null, $landlordId),
            'charges' => $charges,
            'disconnection' => $this->disconnectionForMeter($meter->id, $landlordId),
        ];
    }

    /**
     * Phase-97: the connection's water-client charge history, in the same shape as
     * the tenant chargeHistory() so the shared WaterChargesCard renders it verbatim.
     *
     * @return list<array{period:?string, water_due:float, paid:bool, status:string}>
     */
    public function chargeHistoryForConnection(WaterConnection $connection): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS - 1)->toDateString();

        // Phase-98: charges are now real invoices (water_connection_id), the same
        // shape the tenant chargeHistory() returns from invoices.
        return DB::table('invoices')
            ->where('water_connection_id', $connection->id)
            ->whereNull('voided_at')
            ->whereNull('deleted_at')
            ->where('billing_period_start', '>=', $start)
            ->orderByDesc('billing_period_start')
            ->get(['billing_period_start', 'water_due', 'total_due', 'amount_paid', 'status'])
            ->map(fn ($inv) => [
                'period' => $inv->billing_period_start ? Carbon::parse($inv->billing_period_start)->format('Y-m') : null,
                'water_due' => round((float) $inv->water_due, 2),
                'paid' => (float) $inv->amount_paid >= (float) $inv->total_due,
                'status' => (string) $inv->status,
            ])->all();
    }

    /**
     * The shared shape for a line with no readable meter (flat-rate, not yet
     * metered, decommissioned, or — defensively — a foreign meter id). Charges are
     * passed in because they're meter-independent (a flat-rate line still bills).
     *
     * @param  list<array{period:?string, water_due:float, paid:bool, status:string}>  $charges
     * @return array<string, mixed>
     */
    private function emptyAccount(array $charges = []): array
    {
        return [
            'history' => $this->buildBuckets(collect()),
            'summary' => ['latest_consumption' => null, 'latest_date' => null, 'avg_monthly' => 0, 'ytd_consumption' => 0],
            'alert' => null,
            'charges' => $charges,
            'disconnection' => ['disconnected' => false, 'reason' => null],
        ];
    }

    /**
     * @return list<array{label:string, value:int, period:string}>
     */
    public function consumptionHistory(int $unitId, ?string $since = null, ?string $until = null): array
    {
        return $this->historyFor('unit_id', $unitId, $since, $until);
    }

    /**
     * @return array{latest_consumption:?int, latest_date:?string, avg_monthly:int, ytd_consumption:int}
     */
    public function summary(int $unitId, ?string $since = null, ?string $until = null): array
    {
        return $this->summaryFor('unit_id', $unitId, $since, $until);
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
        return $this->anomalyFor('unit_id', $unitId, $since, $until);
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
     * The unit meter's service-disconnection state (Phase-90).
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

        return $this->disconnectionFrom($meter);
    }

    /**
     * @return list<array{label:string, value:int, period:string}>
     */
    private function historyFor(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): array
    {
        $cacheKey = $this->key($column, $id, $since, $until, $landlordId);
        if (isset($this->historyCache[$cacheKey])) {
            return $this->historyCache[$cacheKey];
        }

        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS - 1);

        $rows = $this->boundedReadings($column, $id, $since, $until, $landlordId)
            ->where('reading_date', '>=', $start->toDateString())
            ->selectRaw('YEAR(reading_date) as y, MONTH(reading_date) as m, SUM(COALESCE(consumption, 0)) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => ((int) $r->y).'-'.((int) $r->m));

        return $this->historyCache[$cacheKey] = $this->buildBuckets($rows);
    }

    /**
     * @return array{latest_consumption:?int, latest_date:?string, avg_monthly:int, ytd_consumption:int}
     */
    private function summaryFor(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): array
    {
        $latest = $this->latestReading($column, $id, $since, $until, $landlordId);

        $values = array_column($this->historyFor($column, $id, $since, $until, $landlordId), 'value');
        $nonZero = array_filter($values, fn ($v) => $v > 0);
        $avgMonthly = $nonZero !== [] ? (int) round(array_sum($nonZero) / count($nonZero)) : 0;

        // Year-to-date, but never before the service window started.
        $ytdFloor = Carbon::now()->startOfYear()->toDateString();
        if ($since !== null && $since > $ytdFloor) {
            $ytdFloor = $since;
        }
        $ytd = (float) $this->boundedReadings($column, $id, null, $until, $landlordId)
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
     * @return array{consumption:int, reading_date:?string}|null
     */
    private function anomalyFor(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): ?array
    {
        $latest = $this->latestReading($column, $id, $since, $until, $landlordId);

        if ($latest === null || ! (bool) $latest->is_anomalous) {
            return null;
        }

        return [
            'consumption' => (int) round((float) $latest->total),
            'reading_date' => Carbon::parse($latest->reading_date)->toDateString(),
        ];
    }

    /**
     * @param  Collection<string, object>  $rows  keyed "Y-n" => {total}
     * @return list<array{label:string, value:int, period:string}>
     */
    private function buildBuckets(Collection $rows): array
    {
        $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(self::HISTORY_MONTHS - 1);

        $history = [];
        $cursor = $start->copy();
        for ($i = 0; $i < self::HISTORY_MONTHS; $i++) {
            $history[] = [
                'label' => $cursor->format('M'),
                'value' => (int) round((float) ($rows->get($cursor->year.'-'.$cursor->month)?->total ?? 0)),
                'period' => $cursor->format('Y-m'),
            ];
            $cursor->addMonth();
        }

        return $history;
    }

    /**
     * A specific meter's disconnection state — the water-client line's own meter
     * (looked up by id, not by unit, since a client line need not anchor a unit).
     *
     * @return array{disconnected:bool, reason:?string}
     */
    private function disconnectionForMeter(int $meterId, ?int $landlordId = null): array
    {
        $meter = DB::table('water_meters')
            ->where('id', $meterId)
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId))
            ->whereNull('deleted_at')
            ->first(['disconnected_at', 'disconnect_reason']);

        return $this->disconnectionFrom($meter);
    }

    /**
     * @return array{disconnected:bool, reason:?string}
     */
    private function disconnectionFrom(?object $meter): array
    {
        return [
            'disconnected' => $meter !== null && $meter->disconnected_at !== null,
            'reason' => $meter->disconnect_reason ?? null,
        ];
    }

    private function latestReading(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): ?object
    {
        $cacheKey = $this->key($column, $id, $since, $until, $landlordId);
        if (array_key_exists($cacheKey, $this->latestCache)) {
            return $this->latestCache[$cacheKey];
        }

        return $this->latestCache[$cacheKey] = $this->boundedReadings($column, $id, $since, $until, $landlordId)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->selectRaw('COALESCE(consumption, 0) as total, reading_date, is_anomalous')
            ->first();
    }

    /**
     * Approved readings for the account, bounded to the service window so one
     * account never sees another's history on the same physical unit or meter.
     */
    private function boundedReadings(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): \Illuminate\Database\Query\Builder
    {
        return DB::table('water_readings')
            ->where($column, $id)
            ->where('status', 'approved')
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId))
            ->when($since !== null, fn ($q) => $q->where('reading_date', '>=', $since))
            ->when($until !== null, fn ($q) => $q->where('reading_date', '<=', $until));
    }

    private function key(string $column, int $id, ?string $since, ?string $until, ?int $landlordId = null): string
    {
        return $column.'|'.$id.'|'.($since ?? '').'|'.($until ?? '').'|'.($landlordId ?? '');
    }
}
