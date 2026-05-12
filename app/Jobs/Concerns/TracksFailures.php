<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Services\MetricsService;
use Throwable;

/**
 * Phase-16 QUEUE-7: per-job-class failure counter. Each ShouldQueue
 * job's failed() hook calls $this->recordJobFailure($exception) to
 * emit a Phase-14 MetricsService counter so ops can graph
 * 'job_failed{class=X,reason=Y}' over time.
 *
 * Failed-jobs total count is already a gauge (CaptureQueueDepth);
 * this trait gives us the per-class breakdown.
 *
 * Defensive: wrapped in try/catch so a metrics outage cannot break
 * the underlying failed() hook (which still needs to log + clean up).
 */
trait TracksFailures
{
    protected function recordJobFailure(Throwable $exception): void
    {
        try {
            app(MetricsService::class)->increment('job_failed', labels: [
                'class' => static::class,
                'reason' => $this->classifyException($exception),
            ]);
        } catch (Throwable) {
            // Metrics best-effort — never break the failed() hook.
        }
    }

    private function classifyException(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof \Illuminate\Http\Client\ConnectionException => 'connection',
            $exception instanceof \Illuminate\Database\QueryException => 'query',
            $exception instanceof \App\Exceptions\Resilience\CircuitOpenException => 'circuit_open',
            $exception instanceof \Symfony\Component\Process\Exception\ProcessTimedOutException => 'timeout',
            default => class_basename($exception),
        };
    }
}
