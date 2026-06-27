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

    private int $totalAccepted = 0;

    private int $totalRejected = 0;

    private int $totalRetryable = 0;

    private string $advancedTo = '';

    public function handle(
        AnalyticsForwarderInterface $forwarder,
        MetricsService $metrics,
        AlertFiringRecorder $recorder,
    ): int {
        $chunk = max(10, (int) $this->option('chunk'));
        $maxRuntime = (int) $this->option('max-runtime-seconds');

        $lastReplayedAt = Cache::get(self::CURSOR_KEY)
            ?? now()->subDay()->toIso8601String();
        $this->advancedTo = $lastReplayedAt;
        $cutoff = now()->subMinutes(5);

        $start = microtime(true);

        ProductEvent::query()
            ->withoutGlobalScopes()
            ->where('created_at', '>', $lastReplayedAt)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->chunkById($chunk, $this->buildChunkCallback($forwarder, $start, $maxRuntime));

        Cache::put(self::CURSOR_KEY, $this->advancedTo, now()->addDays(7));

        $vendor = $forwarder->vendor();
        $totals = $this->buildTotals();

        $this->emitMetrics($metrics, $vendor, $totals, $start);
        $this->emitSummaryLine($vendor, $totals);
        $this->handleFlapAlert($recorder, $vendor, $totals);

        return self::SUCCESS;
    }

    private function buildChunkCallback(
        AnalyticsForwarderInterface $forwarder,
        float $start,
        int $maxRuntime,
    ): \Closure {
        return function ($rows) use ($forwarder, $start, $maxRuntime) {
            if (microtime(true) - $start > $maxRuntime) {
                return false; // stop chunkById
            }

            $events = $rows->map(fn (ProductEvent $event) => $this->mapEventRow($event))->all();

            $result = $forwarder->flush($events);

            $this->totalAccepted += $result['accepted'];
            $this->totalRejected += $result['rejected'];
            $this->totalRetryable += $result['retryable'];

            if ($result['retryable'] === 0) {
                // Advance the cursor only when nothing in this chunk
                // needs retry — preserves at-least-once semantics.
                $latest = $rows->last();
                if ($latest && $latest->created_at) {
                    $this->advancedTo = $latest->created_at->toIso8601String();
                }
            }
        };
    }

    private function mapEventRow(ProductEvent $event): array
    {
        return [
            'distinct_id' => (string) ($event->landlord_id ?? $event->user_id ?? 'anonymous'),
            'event' => $event->event_name,
            'properties' => array_merge(
                $event->properties ?? [],
                ['$lib' => 'propmanager-replay'],
            ),
            'timestamp' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /** @return array{accepted:int, rejected:int, retryable:int, attempted:int, error_rate:float} */
    private function buildTotals(): array
    {
        $attempted = $this->totalAccepted + $this->totalRejected + $this->totalRetryable;
        $errorRate = $attempted > 0
            ? round(($this->totalRejected + $this->totalRetryable) / $attempted, 4)
            : 0.0;

        return [
            'accepted' => $this->totalAccepted,
            'rejected' => $this->totalRejected,
            'retryable' => $this->totalRetryable,
            'attempted' => $attempted,
            'error_rate' => $errorRate,
        ];
    }

    private function emitMetrics(MetricsService $metrics, string $vendor, array $totals, float $start): void
    {
        $metrics->gauge('analytics_events_forwarded_total', $totals['accepted'], ['vendor' => $vendor]);
        $metrics->gauge('analytics_replay_batch_duration_seconds', round(microtime(true) - $start, 2));
        $metrics->gauge('analytics_forwarder_error_rate', $totals['error_rate'], ['vendor' => $vendor]);
    }

    private function emitSummaryLine(string $vendor, array $totals): void
    {
        $this->line(sprintf(
            'vendor=%s accepted=%d rejected=%d retryable=%d error_rate=%s cursor_at=%s',
            $vendor,
            $totals['accepted'],
            $totals['rejected'],
            $totals['retryable'],
            $totals['error_rate'],
            $this->advancedTo,
        ));
    }

    private function handleFlapAlert(AlertFiringRecorder $recorder, string $vendor, array $totals): void
    {
        if ($vendor !== 'noop' && $totals['attempted'] >= 10 && $totals['error_rate'] > self::FLAP_THRESHOLD) {
            $recorder->record(
                alertKey: 'vendor_flap',
                value: $totals['error_rate'],
                threshold: self::FLAP_THRESHOLD,
                metadata: [
                    'vendor' => $vendor,
                    'rejected' => $totals['rejected'],
                    'retryable' => $totals['retryable'],
                ],
            );
        } else {
            $recorder->resolve('vendor_flap');
        }
    }
}
