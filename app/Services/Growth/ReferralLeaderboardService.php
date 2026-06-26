<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-66 REFERRAL-LEADERBOARD-1: rank top referrers on the Phase-34
 * referrals ledger.
 *
 * Privacy is enforced at the SERVICE boundary, never in the view:
 *  - referrers who set leaderboard_opt_out are excluded entirely (DPA
 *    right not to be displayed);
 *  - when $anonymise is true the payload carries NO name/email at all —
 *    only rank + score — so identities cannot leak via client
 *    inspection of the JSON;
 *  - the viewer's OWN row is always returned de-anonymised + flagged
 *    is_self, even when it falls outside the top-N, so a landlord can
 *    always see exactly where they stand.
 */
class ReferralLeaderboardService
{
    public const CACHE_TTL = 600;

    private const GEN_KEY = 'referral:leaderboard:gen';

    /**
     * @return array{
     *     entries: array<int, array{rank:int, score:int, attributed:int, rewarded:int, is_self:bool, name:?string}>,
     *     viewer: ?array{rank:int, score:int, attributed:int, rewarded:int, is_self:bool, name:string},
     *     total_ranked: int
     * }
     */
    public function topReferrers(int $limit, bool $anonymise, ?int $viewerId = null): array
    {
        $limit = max(1, min($limit, (int) config('referral.leaderboard.max', 50)));

        $scored = $this->scoredReferrers();

        $entries = [];
        $viewer = null;

        foreach ($scored as $index => $row) {
            $rank = $index + 1;
            $isSelf = $viewerId !== null && $row['user_id'] === $viewerId;

            if ($rank <= $limit) {
                $entries[] = $this->present($row, $rank, $anonymise, $isSelf);
            }

            if ($isSelf) {
                // The viewer's own row is always de-anonymised.
                $viewer = $this->present($row, $rank, anonymise: false, isSelf: true);
            }
        }

        return [
            'entries' => $entries,
            'viewer' => $viewer,
            'total_ranked' => count($scored),
        ];
    }

    /**
     * Invalidate the cached board by rolling the generation stamp
     * embedded in the scored-list cache key (Phase-54 version-stamp
     * pattern). O(1) and store-agnostic — the old key ages out via TTL.
     * Stored forever so the stamp can never silently regress to '0' and
     * resurrect an opted-out referrer — this is a DPA control.
     */
    public function flushCache(): void
    {
        Cache::forever(self::GEN_KEY, (string) now()->getTimestampMs());
    }

    private function generation(): string
    {
        return (string) Cache::get(self::GEN_KEY, '0');
    }

    /**
     * The scored, ranked, opt-out-filtered referrer list — cached ONCE
     * per generation (viewer/limit/anonymise are deliberately NOT in the
     * key) so a single hot entry serves every viewer. The cheap
     * per-request presentation (masking + self overlay) is applied in
     * topReferrers().
     *
     * @return list<array{user_id:int, name:string, score:int, attributed:int, rewarded:int}>
     */
    private function scoredReferrers(): array
    {
        $key = 'referral:leaderboard:scored:'.$this->generation();

        return Cache::remember($key, self::CACHE_TTL, fn () => $this->buildScored());
    }

    /**
     * @return list<array{user_id:int, name:string, score:int, attributed:int, rewarded:int}>
     */
    private function buildScored(): array
    {
        $rewardWeight = (int) config('referral.leaderboard.reward_weight', 2);

        // Status strings are class constants (not user input) so inlining
        // them in the conditional aggregate is injection-safe.
        $aggregates = Referral::query()
            ->whereIn('status', [Referral::STATUS_ATTRIBUTED, Referral::STATUS_REWARDED])
            ->groupBy('referrer_user_id')
            ->selectRaw('referrer_user_id')
            ->selectRaw("SUM(CASE WHEN status = '".Referral::STATUS_ATTRIBUTED."' THEN 1 ELSE 0 END) as attributed_count")
            ->selectRaw("SUM(CASE WHEN status = '".Referral::STATUS_REWARDED."' THEN 1 ELSE 0 END) as rewarded_count")
            ->get();

        if ($aggregates->isEmpty()) {
            return [];
        }

        // Resolve names + drop opted-out referrers in one query.
        $eligibleUsers = User::query()
            ->whereIn('id', $aggregates->pluck('referrer_user_id'))
            ->where('leaderboard_opt_out', false)
            ->pluck('name', 'id');

        $scored = [];
        foreach ($aggregates as $agg) {
            $entry = $this->scoreAggregate($agg, $eligibleUsers, $rewardWeight);

            if ($entry !== null) {
                $scored[] = $entry;
            }
        }

        // Rank by score desc, then attributed desc, then user_id asc so
        // ties are deterministic regardless of aggregate row order.
        usort($scored, $this->rankingComparator());

        return $scored;
    }

    /**
     * Score a single aggregate row, returning null when the user is
     * opted-out, missing, or has a non-positive score.
     *
     * @param  \Illuminate\Support\Collection<int,string>  $eligibleUsers
     * @return array{user_id:int, name:string, score:int, attributed:int, rewarded:int}|null
     */
    private function scoreAggregate(mixed $agg, \Illuminate\Support\Collection $eligibleUsers, int $rewardWeight): ?array
    {
        $userId = (int) $agg->referrer_user_id;

        if (! $eligibleUsers->has($userId)) {
            return null; // opted-out or missing user
        }

        $attributed = (int) $agg->attributed_count;
        $rewarded = (int) $agg->rewarded_count;
        $score = $attributed + ($rewarded * $rewardWeight);

        if ($score <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'name' => (string) $eligibleUsers->get($userId),
            'score' => $score,
            'attributed' => $attributed,
            'rewarded' => $rewarded,
        ];
    }

    /**
     * Comparator for usort: score desc, then attributed desc, then
     * user_id asc — ties are deterministic regardless of aggregate row order.
     *
     * @return callable(array{user_id:int,score:int,attributed:int}, array{user_id:int,score:int,attributed:int}): int
     */
    private function rankingComparator(): callable
    {
        return fn ($a, $b) => ($b['score'] <=> $a['score'])
            ?: ($b['attributed'] <=> $a['attributed'])
            ?: ($a['user_id'] <=> $b['user_id']);
    }

    /**
     * @param  array{user_id:int, name:string, score:int, attributed:int, rewarded:int}  $row
     * @return array{rank:int, score:int, attributed:int, rewarded:int, is_self:bool, name:?string}
     */
    private function present(array $row, int $rank, bool $anonymise, bool $isSelf): array
    {
        $revealName = $isSelf || ! $anonymise;

        return [
            'rank' => $rank,
            'score' => $row['score'],
            'attributed' => $row['attributed'],
            'rewarded' => $row['rewarded'],
            'is_self' => $isSelf,
            // null when anonymised — the client renders a generic
            // "Referrer #rank" label so no identity ever crosses the wire.
            'name' => $revealName ? $row['name'] : null,
        ];
    }
}
