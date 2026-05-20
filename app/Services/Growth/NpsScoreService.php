<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\NpsPromptState;
use App\Models\NpsResponse;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Phase-66 GROWTH-OBSERVABILITY-1: Net Promoter Score aggregates for a
 * rolling window, platform-wide or for one landlord's user base.
 *
 * NPS = %promoters − %detractors (canonical −100..100). response_rate is
 * responses ÷ recently-prompted users (NpsPromptState.last_prompted_at
 * in window) — a proxy for "of the people we asked, how many answered".
 *
 * The cross-tenant read bypasses only the TenantScope 'landlord' scope
 * (SoftDeletes stays on) so a platform roll-up sees every landlord even
 * when invoked inside an authenticated request.
 */
class NpsScoreService
{
    /**
     * @return array{score:int, response_count:int, response_rate:float, breakdown: array{promoter:int, passive:int, detractor:int}}
     */
    public function compute(?int $landlordId, int $windowDays = 90): array
    {
        $start = Carbon::now()->subDays(max(1, $windowDays));

        // Aggregate in SQL (3 rows max) rather than materialising the whole
        // window into PHP — this is a platform-wide read on a nightly cron.
        $counts = NpsResponse::query()
            ->withoutGlobalScope('landlord')
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId))
            ->where('responded_at', '>=', $start)
            ->groupBy('category')
            ->selectRaw('category, COUNT(*) as total')
            ->pluck('total', 'category');

        $promoters = (int) ($counts[NpsResponse::CATEGORY_PROMOTER] ?? 0);
        $passives = (int) ($counts[NpsResponse::CATEGORY_PASSIVE] ?? 0);
        $detractors = (int) ($counts[NpsResponse::CATEGORY_DETRACTOR] ?? 0);
        $total = $promoters + $passives + $detractors;

        $score = $total > 0 ? (int) round((($promoters - $detractors) / $total) * 100) : 0;

        $prompts = $this->promptCount($landlordId, $start);
        $responseRate = $prompts > 0 ? round($total / $prompts, 4) : 0.0;

        return [
            'score' => $score,
            'response_count' => $total,
            'response_rate' => $responseRate,
            'breakdown' => [
                'promoter' => $promoters,
                'passive' => $passives,
                'detractor' => $detractors,
            ],
        ];
    }

    /**
     * Distinct landlord_ids with at least one response in the window —
     * the "active" landlords the roll-up emits per-scope gauges for.
     *
     * @return list<int>
     */
    public function activeLandlordIds(int $windowDays = 90): array
    {
        return NpsResponse::query()
            ->withoutGlobalScope('landlord')
            ->where('responded_at', '>=', Carbon::now()->subDays(max(1, $windowDays)))
            ->whereNotNull('landlord_id')
            ->distinct()
            ->pluck('landlord_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function promptCount(?int $landlordId, Carbon $start): int
    {
        $query = NpsPromptState::query()->where('last_prompted_at', '>=', $start);

        if ($landlordId !== null) {
            $userIds = User::query()
                ->where(fn ($q) => $q->where('id', $landlordId)->orWhere('landlord_id', $landlordId))
                ->pluck('id');
            $query->whereIn('user_id', $userIds);
        }

        return $query->count();
    }
}
