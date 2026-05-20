<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Phase-66 COHORT-RETENTION-1: per-acquisition-source retention
 * comparison, layered on {@see ChurnService::cohortsBySource()} — it
 * adds NO cohort SQL of its own.
 *
 * For each source it blends every cohort month into one size-weighted
 * retention curve, then expresses each source as a delta against the
 * organic baseline at the same month-offset ("referral retains +9pts at
 * month 3 vs organic"). Cohorts whose total sample is below
 * growth.cohort.min_sample are flagged so the UI can mute the noise.
 *
 * The global cross-tenant view reads every landlord's users, so it is
 * gated to super admins INSIDE the service (defence in depth on top of
 * the route gate). Landlords get a scoped variant over only the users
 * they referred.
 */
class CohortRetentionService
{
    public function __construct(private ChurnService $churn) {}

    /**
     * Global, cross-tenant source comparison. Super-admin only.
     *
     * @return array{sources: list<array<string, mixed>>, baseline: list<float>, month_range: int, min_sample: int}
     */
    public function sourceComparison(int $monthsBack = 12): array
    {
        $user = Auth::user();
        if (! $user || ! $user->isSuperAdmin()) {
            throw new AuthorizationException('Global cohort retention is restricted to super admins.');
        }

        return $this->aggregate($this->churn->cohortsBySource($monthsBack), $monthsBack);
    }

    /**
     * Source comparison restricted to the users a landlord referred.
     * Safe for any landlord — it can never see another landlord's data.
     *
     * @return array{sources: list<array<string, mixed>>, baseline: list<float>, month_range: int, min_sample: int}
     */
    public function sourceComparisonForLandlord(User $landlord, int $monthsBack = 12): array
    {
        // Only referrals actually credited to this landlord (attributed or
        // rewarded) — matches ReferralLeaderboardService semantics, so the
        // landlord's cohort counts agree with their leaderboard view.
        $referredIds = Referral::query()
            ->where('referrer_user_id', $landlord->id)
            ->whereIn('status', [Referral::STATUS_ATTRIBUTED, Referral::STATUS_REWARDED])
            ->pluck('referred_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->aggregate($this->churn->cohortsBySource($monthsBack, $referredIds), $monthsBack);
    }

    /**
     * Blend cohort rows into one size-weighted curve per source, attach
     * the delta-vs-organic series, and flag thin samples.
     *
     * @param  array<int, array{cohort_month: string, source: string, size: int, retention: array<int, float>}>  $rows
     * @return array{sources: list<array<string, mixed>>, baseline: list<float>, month_range: int, min_sample: int}
     */
    private function aggregate(array $rows, int $monthsBack): array
    {
        $minSample = (int) config('growth.cohort.min_sample', 20);

        /** @var array<string, list<array{size:int, retention: array<int, float>}>> $bySource */
        $bySource = [];
        foreach ($rows as $row) {
            $bySource[$row['source']][] = $row;
        }

        $sources = [];
        foreach ($bySource as $source => $cohortRows) {
            $totalSize = 0;
            $num = []; // offset => Σ(size · retention)
            $den = []; // offset => Σ(size) over cohorts old enough to have that offset

            foreach ($cohortRows as $cohort) {
                $totalSize += $cohort['size'];
                foreach ($cohort['retention'] as $offset => $rate) {
                    if ($offset > $monthsBack) {
                        break;
                    }
                    $num[$offset] = ($num[$offset] ?? 0.0) + $cohort['size'] * $rate;
                    $den[$offset] = ($den[$offset] ?? 0) + $cohort['size'];
                }
            }

            ksort($num);
            $retention = [];
            // Key by true month-offset (not a dense push) so a future
            // sparse-offset cohort can't desync index from offset.
            foreach ($num as $offset => $weighted) {
                $retention[$offset] = $den[$offset] > 0 ? round($weighted / $den[$offset], 4) : 0.0;
            }

            $sources[$source] = [
                'source' => $source,
                'total_size' => $totalSize,
                'retention' => $retention,
                'insufficient_sample' => $totalSize < $minSample,
            ];
        }

        $baseline = $sources['organic']['retention'] ?? [];

        foreach ($sources as &$data) {
            $delta = [];
            foreach ($data['retention'] as $offset => $rate) {
                $delta[] = isset($baseline[$offset]) ? round($rate - $baseline[$offset], 4) : 0.0;
            }
            $data['delta_vs_organic'] = $delta;
        }
        unset($data);

        return [
            'sources' => $this->organicFirst($sources),
            'baseline' => $baseline,
            'month_range' => $monthsBack,
            'min_sample' => $minSample,
        ];
    }

    /**
     * Deterministic order: organic (the baseline) first, then the rest
     * alphabetically.
     *
     * @param  array<string, array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    private function organicFirst(array $sources): array
    {
        $ordered = [];
        if (isset($sources['organic'])) {
            $ordered[] = $sources['organic'];
            unset($sources['organic']);
        }
        ksort($sources);
        foreach ($sources as $row) {
            $ordered[] = $row;
        }

        return $ordered;
    }
}
