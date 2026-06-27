<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-22 PERF-SLO-1: per-request latency instrumentation. Pre-Phase-22
 * the MetricsService histogram (Phase-14 OBSERV-9) was only fed by
 * SlowQueryServiceProvider's db_query_ms — the app could not answer
 * "what is our p95 page latency" because nothing measured end-to-end
 * request time. This middleware emits `http_request_ms` so the SLO
 * tooling (PERF-SLO-2/3) and the k6 load baseline (PERF-LOAD) have a
 * real signal to work against.
 *
 * Label cardinality is deliberately bounded:
 *   - route: the route NAME, not the URI. Raw URIs leak ids (PII) and
 *     explode cardinality; an unnamed route falls back to 'unmatched'.
 *   - status: bucketed to a class (2xx/3xx/4xx/5xx), never the raw code.
 *   - method: the HTTP verb — already low-cardinality.
 *
 * Timing is emitted from terminate() so it covers the full request
 * lifecycle including response send. The observe() call is fail-open:
 * a Redis hiccup must never turn into a 500 (same posture as
 * MetricsService internals).
 */
class RecordRequestLatency
{
    private const START_ATTR = 'perf_slo_start';

    public function __construct(private readonly MetricsService $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::START_ATTR, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $start = $request->attributes->get(self::START_ATTR);
            if (! is_float($start)) {
                return;
            }

            $elapsedMs = (microtime(true) - $start) * 1000;
            $routeName = $request->route()?->getName() ?: 'unmatched';

            $this->metrics->observe('http_request_ms', $elapsedMs, [
                'route' => $routeName,
                'method' => $request->getMethod(),
                'status' => $this->statusClass($response->getStatusCode()),
            ]);

            // Phase-35 PLATFORM-ANALYTICS-2: fire a page_view product
            // event sampled by config('platform.analytics_sample_rate',
            // 0.1). Skip health endpoints (noise) + non-2xx responses.
            $this->maybeRecordPageView($request, $response, $routeName);
        } catch (\Throwable) {
            // Fail-open and SILENT. This runs in terminate(), after the
            // response is already sent — the user is unaffected no matter
            // what. Deliberately no logging here: MetricsService::observe
            // already logs its own internal failures, and a second log
            // call from terminate() is fragile (tests that mock the Log
            // facade leave it in a state where Log::channel() can return
            // null). A dropped latency sample is simply not worth the risk.
        }
    }

    private function maybeRecordPageView(Request $request, Response $response, string $routeName): void
    {
        $status = $response->getStatusCode();
        if ($status >= 400) {
            return;
        }
        if ($this->isNoiseRoute($routeName)) {
            return;
        }

        $sampleRate = (float) config('platform.analytics_sample_rate', 0.1);
        if (! $this->passesSampleRate($sampleRate)) {
            return;
        }

        app(\App\Services\Platform\ProductEventTracker::class)->track(
            'page_view',
            [
                'route_name' => $routeName,
                'method' => $request->getMethod(),
                'status' => $this->statusClass($status),
            ],
            $request->user(),
        );
    }

    private function isNoiseRoute(string $routeName): bool
    {
        if (in_array($routeName, ['unmatched', 'api.health', 'api.v1.health'], true)) {
            return true;
        }

        return str_contains($routeName, 'health');
    }

    private function passesSampleRate(float $sampleRate): bool
    {
        if ($sampleRate <= 0.0) {
            return false;
        }
        if ($sampleRate < 1.0 && mt_rand(1, 10000) > (int) ($sampleRate * 10000)) {
            return false;
        }

        return true;
    }

    private function statusClass(int $status): string
    {
        return intdiv($status, 100).'xx';
    }
}
