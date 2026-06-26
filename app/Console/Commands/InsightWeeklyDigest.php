<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\WeeklyInsightDigestMailable;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Insight\InsightDashboardService;
use App\Services\MetricsService;
use App\Services\Platform\LifecycleOptInChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-37 PWA-DIGEST-1: weekly insight digest cron. Iterates
 * paying landlords on Monday morning, composes a per-landlord
 * summary via InsightDashboardService::landlordSummary, gates
 * via LifecycleOptInChecker, and queues WeeklyInsightDigest
 * Mailable. Cache::add idempotency keyed on landlord_id + ISO
 * week prevents double-send on retries.
 */
class InsightWeeklyDigest extends Command
{
    protected $signature = 'insight:weekly-digest {--dry-run}';

    protected $description = 'Phase-37 PWA-DIGEST-1: queue weekly insight digest to opted-in paying landlords.';

    private InsightDashboardService $insights;

    private LifecycleOptInChecker $optInChecker;

    private bool $dryRun;

    private string $isoWeek;

    public function handle(
        InsightDashboardService $insights,
        LifecycleOptInChecker $optInChecker,
        MetricsService $metrics,
    ): int {
        $this->insights = $insights;
        $this->optInChecker = $optInChecker;
        $this->dryRun = (bool) $this->option('dry-run');
        $this->isoWeek = now()->format('o-W');

        $sent = 0;
        $skippedOptIn = 0;
        $skippedNoSummary = 0;

        $payingLandlordIds = Subscription::query()
            ->whereNull('cancelled_at')
            ->pluck('user_id')
            ->unique();

        User::query()
            ->where('role', 'landlord')
            ->whereIn('id', $payingLandlordIds)
            ->whereNotNull('email')
            ->chunkById(100, function ($landlords) use (
                &$sent,
                &$skippedOptIn,
                &$skippedNoSummary,
            ) {
                foreach ($landlords as $landlord) {
                    $outcome = $this->digestOutcomeForLandlord($landlord);

                    if ($outcome === 'skipped_optin') {
                        $skippedOptIn++;
                    } elseif ($outcome === 'skipped_no_summary') {
                        $skippedNoSummary++;
                    } elseif ($outcome === 'sent') {
                        $sent++;
                    }
                }
            });

        $this->recordMetrics($metrics, $sent, $skippedOptIn, $skippedNoSummary);

        $this->info(sprintf(
            'Queued %d insight digest(s). Skipped %d opt-out, %d no-summary.%s',
            $sent,
            $skippedOptIn,
            $skippedNoSummary,
            $this->dryRun ? ' [dry-run]' : '',
        ));

        return self::SUCCESS;
    }

    private function digestOutcomeForLandlord(User $landlord): string
    {
        if (! $this->optInChecker->allows($landlord)) {
            return 'skipped_optin';
        }

        $cacheKey = sprintf('insight:digest:%d:%s', $landlord->id, $this->isoWeek);
        if (! Cache::add($cacheKey, true, now()->addDays(8))) {
            return 'duplicate';
        }

        $summary = $this->insights->landlordSummary($landlord->id);
        if (empty($summary)) {
            return 'skipped_no_summary';
        }

        if (! $this->dryRun) {
            Mail::to($landlord->email)->queue(
                new WeeklyInsightDigestMailable($landlord, $summary),
            );
        }

        return 'sent';
    }

    private function recordMetrics(
        MetricsService $metrics,
        int $sent,
        int $skippedOptIn,
        int $skippedNoSummary,
    ): void {
        $metrics->gauge('insight_digest_sent_count', $sent);
        $metrics->gauge('insight_digest_skipped_optin_count', $skippedOptIn);
        $metrics->gauge('insight_digest_skipped_no_summary_count', $skippedNoSummary);
    }
}
