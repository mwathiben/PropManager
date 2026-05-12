<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Phase-16 QUEUE-6: capture per-queue depth + failed-jobs count as
 * Prometheus gauges so Grafana can graph them over time.
 *
 * Counterpart to the Phase-5 OBS-13 failed_jobs growth-monitor email
 * alert — that surfaces a single daily threshold; this command writes
 * a continuous time-series so ops can plot the trend.
 *
 * Scheduled via routes/console.php every minute.
 */
class CaptureQueueDepth extends Command
{
    protected $signature = 'metrics:capture-queue-depth';

    protected $description = 'Phase-16 QUEUE-6: capture per-queue depth + failed-jobs count as Prometheus gauges.';

    public function handle(MetricsService $metrics): int
    {
        $queues = config('queue.health.queues', ['default']);

        foreach ($queues as $queue) {
            try {
                $depth = Queue::size($queue);
                $metrics->gauge('queue_depth', (float) $depth, ['queue' => $queue]);
            } catch (\Throwable $e) {
                $this->warn("queue depth capture failed for '{$queue}': {$e->getMessage()}");
            }
        }

        try {
            $totalFailed = (int) DB::table('failed_jobs')->count();
            $lastHour = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();
            $lastDay = (int) DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            $metrics->gauge('failed_jobs_total', (float) $totalFailed, ['age_bucket' => 'all']);
            $metrics->gauge('failed_jobs_total', (float) $lastHour, ['age_bucket' => 'last_hour']);
            $metrics->gauge('failed_jobs_total', (float) $lastDay, ['age_bucket' => 'last_day']);
        } catch (\Throwable $e) {
            $this->warn("failed_jobs gauge capture failed: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }
}
