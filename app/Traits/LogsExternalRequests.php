<?php

namespace App\Traits;

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
    }
}
