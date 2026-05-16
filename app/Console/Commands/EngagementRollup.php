<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LandlordEngagementScore;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\EngagementScoreService;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-34 GROWTH-ENGAGEMENT-2/3: per-landlord engagement rollup.
 *
 *   - Walks every active landlord, calls EngagementScoreService::
 *     snapshot(today), persists one row per landlord.
 *   - Emits landlord_engagement_score{landlord_id} gauge for top 50
 *     by descending score.
 *   - Fires low_engagement_landlord (sev4) when any PAYING landlord
 *     drops below score 30. The paying-only filter keeps the alert
 *     noise low — free-tier landlords sometimes have score 0 for
 *     legitimate reasons (read-only access, monthly review only).
 */
class EngagementRollup extends Command
{
    protected $signature = 'engagement:rollup {--threshold=30}';

    protected $description = 'Phase-34 GROWTH-ENGAGEMENT-2: per-landlord engagement score gauge + low-engagement alert.';

    public function handle(EngagementScoreService $service, MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $threshold = max(0, min(100, (int) $this->option('threshold')));

        $landlordIds = User::query()
            ->where('role', 'landlord')
            ->whereNull('archived_at')
            ->pluck('id');

        $scores = [];
        foreach ($landlordIds as $landlordId) {
            $snapshot = $service->snapshot((int) $landlordId);
            $scores[(int) $landlordId] = $snapshot->score;
        }

        arsort($scores);
        $topN = array_slice($scores, 0, 50, true);
        foreach ($topN as $landlordId => $score) {
            $metrics->gauge(
                'landlord_engagement_score',
                (float) $score,
                ['landlord_id' => (string) $landlordId],
            );
        }

        $payingOffenders = [];
        $payingLandlordIds = Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereNull('cancelled_at')
            ->pluck('user_id')
            ->all();
        foreach ($scores as $landlordId => $score) {
            if ($score < $threshold && in_array($landlordId, $payingLandlordIds, true)) {
                $payingOffenders[$landlordId] = $score;
            }
        }

        if ($payingOffenders !== []) {
            $worst = min($payingOffenders);
            $recorder->record(
                alertKey: 'low_engagement_landlord',
                value: (float) $worst,
                threshold: (float) $threshold,
                metadata: ['landlord_ids' => array_keys($payingOffenders)],
            );
        } else {
            $recorder->resolve('low_engagement_landlord');
        }

        $this->info(sprintf(
            'Audited %d landlord(s). top=%s low_paying=%d',
            count($scores),
            $scores === [] ? '-' : (string) max($scores),
            count($payingOffenders),
        ));

        return self::SUCCESS;
    }
}
