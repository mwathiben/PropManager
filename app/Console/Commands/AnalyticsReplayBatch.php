<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductEvent;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use App\Services\Vendors\AnalyticsForwarderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-39 VENDOR-ANALYTICS-2: daily replay cron. Reads
 * product_events created since the last successful run (5min
 * buffer for late writes), chunks of 100, calls the bound
 * AnalyticsForwarderInterface (PostHog, default noop). The
 * last_replayed_at cursor lives in cache so the next run picks
 * up exactly where the previous left off — including partial-
 * failure retry behaviour.
 *
 * Fires VENDOR-OBSERV-2 vendor_flap (sev4) when the run's
 * error rate exceeds 10%.
 */
class AnalyticsReplayBatch extends Command
{
    private const CURSOR_KEY = 'vendors:analytics:last-replayed-at';

    private const FLAP_THRESHOLD = 0.10;

    protected $signature = 'analytics:replay-batch {--chunk=100} {--max-runtime-seconds=180}';

    protected $description = 'Phase-39 VENDOR-ANALYTICS-2: forward product_events to the configured analytics vendor.';

    public function handle(
        AnalyticsForwarderInterface $forwarder,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $chunk = max(10, (int) $this->option('chunk'));
        $maxRuntime = (int) $this->option('max-runtime-seconds');

        $lastReplayedAt = Cache::get(self::CURSOR_KEY)
            ?? now()->subDay()->toIso8601String();
        $cutoff = now()->subMinutes(5);

        $start = microtime(true);
        $totalAccepted = 0;
        $totalRejected = 0;
        $totalRetryable = 0;
        $advancedTo = $lastReplayedAt;

        ProductEvent::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>', $lastReplayedAt)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->chunkById($chunk, function ($rows) use (
                $forwarder,
                &$totalAccepted,
                &$totalRejected,
                &$totalRetryable,
                &$advancedTo,
                $start,
                $maxRuntime,
            ) {
                if (microtime(true) - $start > $maxRuntime) {
                    return false; // stop chunkById
                }

                $events = $rows->map(fn (ProductEvent $event) => [
                    'distinct_id' => (string) ($event->landlord_id ?? $event->user_id ?? 'anonymous'),
                    'event' => $event->event_name,
                    'properties' => array_merge(
                        $event->properties ?? [],
                        ['$lib' => 'propmanager-replay'],
                    ),
                    'timestamp' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ])->all();

                $result = $forwarder->flush($events);

                $totalAccepted += $result['accepted'];
                $totalRejected += $result['rejected'];
                $totalRetryable += $result['retryable'];

                if ($result['retryable'] === 0) {
                    // Advance the cursor only when nothing in this chunk
                    // needs retry — preserves at-least-once semantics.
                    $latest = $rows->last();
                    if ($latest && $latest->created_at) {
                        $advancedTo = $latest->created_at->toIso8601String();
                    }
                }
            });

        Cache::put(self::CURSOR_KEY, $advancedTo, now()->addDays(7));

        $vendor = $forwarder->vendor();
        $metrics->gauge('analytics_events_forwarded_total', $totalAccepted, ['vendor' => $vendor]);
        $metrics->gauge('analytics_replay_batch_duration_seconds', round(microtime(true) - $start, 2));

        $totalAttempted = $totalAccepted + $totalRejected + $totalRetryable;
        $errorRate = $totalAttempted > 0
            ? round(($totalRejected + $totalRetryable) / $totalAttempted, 4)
            : 0.0;
        $metrics->gauge('analytics_forwarder_error_rate', $errorRate, ['vendor' => $vendor]);

        $this->line(sprintf(
            'vendor=%s accepted=%d rejected=%d retryable=%d error_rate=%s cursor_at=%s',
            $vendor,
            $totalAccepted,
            $totalRejected,
            $totalRetryable,
            $errorRate,
            $advancedTo,
        ));

        if ($vendor !== 'noop' && $totalAttempted >= 10 && $errorRate > self::FLAP_THRESHOLD) {
            $recorder->record(
                alertKey: 'vendor_flap',
                value: $errorRate,
                threshold: self::FLAP_THRESHOLD,
                metadata: [
                    'vendor' => $vendor,
                    'rejected' => $totalRejected,
                    'retryable' => $totalRetryable,
                ],
            );
        } else {
            $recorder->resolve('vendor_flap');
        }

        return self::SUCCESS;
    }
}
