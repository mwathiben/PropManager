<?php

namespace App\Traits;

use App\Services\MetricsService;
use App\Services\Resilience\CircuitBreaker;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

trait LogsExternalRequests
{
    protected int $slowThresholdMs = 5000;

    /**
     * Phase-16 RESIL-1: gate the HTTP call through CircuitBreaker before
     * timing it. The breaker is opt-in per-provider (config 'enabled'
     * defaults to false); when disabled, this is a transparent pass-through.
     *
     * A breaker that's OPEN throws CircuitOpenException — the caller's
     * existing ConnectionException catch + return-null path is the
     * production contract for "gateway unreachable", so all sites that
     * already use timedHttpRequest get fast-fail behaviour for free.
     */
    protected function timedHttpRequest(string $provider, string $endpoint, callable $httpCall): Response
    {
        return app(CircuitBreaker::class)->guard($provider, $endpoint, function () use ($provider, $endpoint, $httpCall) {
            return $this->measureHttpCall($provider, $endpoint, $httpCall);
        });
    }

    private function measureHttpCall(string $provider, string $endpoint, callable $httpCall): Response
    {
        $startTime = microtime(true);

        try {
            $response = $httpCall();
            $this->logExternalCallDuration($provider, $endpoint, $startTime, $response->status());

            return $response;
        } catch (\Throwable $e) {
            $this->logExternalCallDuration($provider, $endpoint, $startTime, 0);
            throw $e;
        }
    }

    private function logExternalCallDuration(string $provider, string $endpoint, float $startTime, int $statusCode): void
    {
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $level = $durationMs > $this->slowThresholdMs ? 'warning' : 'info';

        Log::$level('External API call completed', [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'duration_ms' => $durationMs,
            'status_code' => $statusCode,
        ]);

        // OBS-8: feed external-API durations into the metrics sink so a
        // Twilio / Paystack / M-Pesa slowdown is visible without grepping
        // laravel.log. Hard fail-closed: the metrics emit cannot under
        // any circumstance interfere with the calling payment / webhook
        // path, so we swallow every exception (Redis down, container
        // not bound in unit tests, etc.). Counters: total calls bucketed
        // by status family, plus a separate slow-call counter.
        try {
            $bucket = match (true) {
                $statusCode === 0 => 'error',
                $statusCode >= 500 => 'server',
                $statusCode >= 400 => 'client',
                $statusCode >= 200 => 'ok',
                default => 'other',
            };
            $metrics = app(MetricsService::class);
            $metrics->increment('external_api.calls', labels: ['provider' => $provider, 'status' => $bucket]);
            if ($durationMs > $this->slowThresholdMs) {
                $metrics->increment('external_api.slow', labels: ['provider' => $provider]);
            }
        } catch (\Throwable) {
            // Metrics is best-effort; never block the caller.
        }
    }
}
