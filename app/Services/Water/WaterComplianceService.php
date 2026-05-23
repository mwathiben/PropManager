<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase-92 WATER-COMPLIANCE: borehole regulatory compliance for the landlord —
 * for each borehole-supplied building, the WRA abstraction permit + water-quality
 * certificate (expiry tracked via the Phase-82 document lifecycle) and the annual
 * abstraction limit vs actual abstraction (production).
 *
 * Strictly landlord-scoped. "Borehole building" = effective water_source resolves
 * to 'borehole' (building override else the global PaymentConfiguration). All sums
 * are batched grouped queries (no N+1); abstraction "used" prefers the building's
 * main meter (the abstraction point) and falls back to summed unit consumption.
 * Honesty over flattery (Phase-91 lesson): no_limit / unknown are distinct from a
 * real number — never a fabricated "0% used" or "compliant".
 */
class WaterComplianceService
{
    private const PERMIT_TYPE = 'wra_abstraction_permit';

    private const CERT_TYPE = 'water_quality_certificate';

    /** Utilization at/above this % (or projected to exceed) is a warning. */
    private const WARN_PCT = 90.0;

    /**
     * A unit-meter estimate is a LOWER bound (excludes leaks/common-area draw), so
     * once it has already consumed this share of the limit we will not show a
     * confident "within limit" — the true abstraction may already be over.
     */
    private const ESTIMATE_WARN_PCT = 75.0;

    /**
     * Don't extrapolate an annual projection until this fraction of the year has
     * elapsed — one reading on 2 Jan must not project to 180x and false-alarm.
     */
    private const MIN_PROJECTION_FRACTION = 0.08;

    /**
     * @return array<string, mixed>
     */
    public function forLandlord(int $landlordId): array
    {
        $globalSource = DB::table('payment_configurations')
            ->where('landlord_id', $landlordId)
            ->value('water_source');

        $borehole = DB::table('buildings')
            ->where('landlord_id', $landlordId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'water_source', 'water_abstraction_limit'])
            ->filter(fn ($b) => ($b->water_source ?: $globalSource) === 'borehole')
            ->values();

        if ($borehole->isEmpty()) {
            return $this->empty();
        }

        $ids = $borehole->pluck('id')->all();
        $yearStart = Carbon::now()->startOfYear()->toDateString();
        $fractionElapsed = Carbon::now()->dayOfYear / Carbon::now()->daysInYear;

        // The abstraction point = a building's MAIN meter (a top-level meter that
        // feeds sub-meters — same definition as Phase-91 non-revenue water). Its
        // reading is total abstraction (vs sub-meters, which only measure
        // distribution). A building-level meter can't hold readings on its own
        // (water_readings.unit_id is NOT NULL), so the main meter carries a unit.
        $mainByBuilding = DB::table('water_readings as wr')
            ->join('water_meters as m', 'm.id', '=', 'wr.meter_id')
            ->where('wr.landlord_id', $landlordId)
            ->whereIn('m.building_id', $ids)
            ->whereNull('m.parent_meter_id')
            ->whereNull('m.deleted_at')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('water_meters as s')
                ->whereColumn('s.parent_meter_id', 'm.id')
                ->whereNull('s.deleted_at'))
            ->where('wr.status', 'approved')
            ->where('wr.reading_date', '>=', $yearStart)
            ->groupBy('m.building_id')
            // water_readings.consumption is NOT NULL, so a present reading always
            // carries a real value (0 = genuinely no usage). cnt drives has_data.
            ->selectRaw('m.building_id as bid, SUM(wr.consumption) as total, COUNT(*) as cnt')
            ->get()->keyBy('bid');

        $unitByBuilding = DB::table('water_readings as wr')
            ->join('units as u', 'u.id', '=', 'wr.unit_id')
            ->where('wr.landlord_id', $landlordId)
            ->whereIn('u.building_id', $ids)
            ->whereNull('u.deleted_at')
            ->where('wr.status', 'approved')
            ->where('wr.reading_date', '>=', $yearStart)
            ->groupBy('u.building_id')
            ->selectRaw('u.building_id as bid, SUM(wr.consumption) as total, COUNT(*) as cnt')
            ->get()->keyBy('bid');

        $docs = Document::query()
            ->where('landlord_id', $landlordId)
            ->where('documentable_type', 'App\\Models\\Building')
            ->whereIn('documentable_id', $ids)
            ->whereIn('document_type', [self::PERMIT_TYPE, self::CERT_TYPE])
            ->whereNull('superseded_by_document_id')
            ->orderByDesc('id')
            ->get();

        $latestDoc = fn (int $bid, string $type) => $docs
            ->first(fn ($d) => (int) $d->documentable_id === $bid && $d->document_type === $type);

        $buildings = $borehole->map(function ($b) use ($mainByBuilding, $unitByBuilding, $fractionElapsed, $latestDoc) {
            $abstraction = $this->abstractionFor($b, $mainByBuilding[$b->id] ?? null, $unitByBuilding[$b->id] ?? null, $fractionElapsed);
            $permit = $this->docPayload($latestDoc((int) $b->id, self::PERMIT_TYPE));
            $cert = $this->docPayload($latestDoc((int) $b->id, self::CERT_TYPE));

            return [
                'building_id' => (int) $b->id,
                'name' => $b->name,
                'overall_status' => $this->overallStatus($abstraction, $permit, $cert),
                'abstraction' => $abstraction,
                'permit' => $permit,
                'quality_cert' => $cert,
            ];
        })->all();

        return [
            'buildings' => $buildings,
            'summary' => $this->summarize($buildings),
        ];
    }

    /**
     * @param  object|null  $main
     * @param  object|null  $units
     * @return array<string, mixed>
     */
    private function abstractionFor($building, $main, $units, float $fractionElapsed): array
    {
        $limit = $building->water_abstraction_limit !== null ? (float) $building->water_abstraction_limit : null;

        if ($main !== null && (int) $main->cnt > 0) {
            $used = (float) $main->total;
            $basis = 'meter';
            $hasData = true;
        } elseif ($units !== null && (int) $units->cnt > 0) {
            $used = (float) $units->total;
            $basis = 'units';
            $hasData = true;
        } else {
            $used = 0.0;
            $basis = null;
            $hasData = false;
        }

        $projected = ($hasData && $fractionElapsed >= self::MIN_PROJECTION_FRACTION)
            ? (int) round($used / $fractionElapsed)
            : null;
        $utilization = ($limit !== null && $limit > 0) ? round($used / $limit * 100, 1) : null;
        $estimate = $basis === 'units';

        return [
            'limit' => $limit,
            'used' => (int) round($used),
            'basis' => $basis,
            'estimate' => $estimate,
            'has_data' => $hasData,
            'utilization_pct' => $utilization,
            'projected_annual' => $projected,
            'status' => $this->abstractionStatus($limit, $used, $hasData, $projected, $utilization, $estimate),
        ];
    }

    private function abstractionStatus(?float $limit, float $used, bool $hasData, ?int $projected, ?float $utilization, bool $isEstimate): string
    {
        if ($limit === null || $limit <= 0) {
            return 'no_limit';
        }
        if (! $hasData) {
            return 'unknown';
        }
        if ($used > $limit) {
            return 'exceeded';
        }
        if (($projected !== null && $projected > $limit) || ($utilization !== null && $utilization >= self::WARN_PCT)) {
            return 'warning';
        }
        // A unit-meter estimate undercounts true abstraction — don't show a
        // confident "within limit" once a high share is already used.
        if ($isEstimate && $utilization !== null && $utilization >= self::ESTIMATE_WARN_PCT) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function docPayload(?Document $doc): ?array
    {
        if ($doc === null) {
            return null;
        }

        return [
            'id' => $doc->id,
            'title' => $doc->title,
            'expires_at' => $doc->expires_at?->toDateString(),
            'expiry_status' => $doc->expiryStatus(),
        ];
    }

    /**
     * @param  array<string, mixed>  $abstraction
     * @param  array<string, mixed>|null  $permit
     * @param  array<string, mixed>|null  $cert
     */
    private function overallStatus(array $abstraction, ?array $permit, ?array $cert): string
    {
        $action = $abstraction['status'] === 'exceeded'
            || $permit === null || $permit['expiry_status'] === 'expired'
            || $cert === null || $cert['expiry_status'] === 'expired';
        if ($action) {
            return 'action';
        }

        // An undated regulatory doc ('none') isn't a clean bill of health — its
        // validity is unknown, so it warns rather than reads green.
        $warning = $abstraction['status'] === 'warning'
            || in_array($permit['expiry_status'] ?? null, ['expiring_soon', 'none'], true)
            || in_array($cert['expiry_status'] ?? null, ['expiring_soon', 'none'], true);

        return $warning ? 'warning' : 'ok';
    }

    /**
     * @param  list<array<string, mixed>>  $buildings
     * @return array<string, int>
     */
    private function summarize(array $buildings): array
    {
        $countDoc = fn (string $key, string $status) => count(array_filter(
            $buildings,
            fn ($b) => ($b[$key]['expiry_status'] ?? 'missing') === $status || ($status === 'missing' && $b[$key] === null)
        ));

        return [
            'borehole_buildings' => count($buildings),
            'attention' => count(array_filter($buildings, fn ($b) => $b['overall_status'] === 'action')),
            'watch' => count(array_filter($buildings, fn ($b) => $b['overall_status'] === 'warning')),
            'permits_missing' => count(array_filter($buildings, fn ($b) => $b['permit'] === null)),
            'permits_expired' => $countDoc('permit', 'expired'),
            'permits_expiring' => $countDoc('permit', 'expiring_soon'),
            'certs_missing' => count(array_filter($buildings, fn ($b) => $b['quality_cert'] === null)),
            'certs_expired' => $countDoc('quality_cert', 'expired'),
            'certs_expiring' => $countDoc('quality_cert', 'expiring_soon'),
            'limits_exceeded' => count(array_filter($buildings, fn ($b) => $b['abstraction']['status'] === 'exceeded')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(): array
    {
        return [
            'buildings' => [],
            'summary' => [
                'borehole_buildings' => 0,
                'attention' => 0,
                'watch' => 0,
                'permits_missing' => 0,
                'permits_expired' => 0,
                'permits_expiring' => 0,
                'certs_missing' => 0,
                'certs_expired' => 0,
                'certs_expiring' => 0,
                'limits_exceeded' => 0,
            ],
        ];
    }
}
