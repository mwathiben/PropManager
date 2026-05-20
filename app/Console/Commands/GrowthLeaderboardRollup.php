<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserTourState;
use App\Services\Growth\ReferralLeaderboardService;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-66 GROWTH-OBSERVABILITY-2: referral-leaderboard participation +
 * onboarding-tour status gauges. Visibility metrics — no alerts.
 */
class GrowthLeaderboardRollup extends Command
{
    protected $signature = 'growth:leaderboard-rollup';

    protected $description = 'Emit referral-leaderboard participation and onboarding-tour status gauges.';

    public function handle(ReferralLeaderboardService $leaderboard, MetricsService $metrics): int
    {
        // limit:1 keeps the payload tiny while total_ranked still counts
        // every opt-in participant.
        $board = $leaderboard->topReferrers(limit: 1, anonymise: true);
        $metrics->gauge('referral_leaderboard_participants', (float) $board['total_ranked']);
        $metrics->gauge('referral_leaderboard_top_score', (float) ($board['entries'][0]['score'] ?? 0));

        $counts = UserTourState::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $metrics->gauge('onboarding_tour_active_count', (float) ($counts[UserTourState::STATUS_ACTIVE] ?? 0));
        $metrics->gauge('onboarding_tour_completed_count', (float) ($counts[UserTourState::STATUS_COMPLETED] ?? 0));
        $metrics->gauge('onboarding_tour_dismissed_count', (float) ($counts[UserTourState::STATUS_DISMISSED] ?? 0));

        $this->info("growth:leaderboard-rollup {$board['total_ranked']} participants; tour status gauges emitted.");

        return self::SUCCESS;
    }
}
