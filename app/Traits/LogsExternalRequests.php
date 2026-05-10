<?php

namespace App\Traits;

use App\Services\MetricsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

trait LogsExternalRequests
{
    protected int $slowThresholdMs = 5000;

    protected function timedHttpRequest(string $provider, string $endpoint, callable $httpCall): Response
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
