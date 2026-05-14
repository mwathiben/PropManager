<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Observability — Phase-14
|--------------------------------------------------------------------------
|
| Configuration for the /metrics Prometheus endpoint (OBSERV-1), the
| Sentry release tagging (OBSERV-2), and supporting plumbing. Keeping
| these in their own config file rather than mixing into security.php
| keeps the boundary clear: security.php is the regulator-inspectable
| surface, observability.php is the ops-facing surface.
|
*/

return [

    'metrics' => [
        // Bearer token for /metrics scrape. Leave empty to disable
        // bearer auth. If both bearer and allow_ips are empty, the
        // endpoint returns 503 (fail-closed).
        'bearer' => env('METRICS_BEARER', ''),

        // Comma-separated CIDR list. Calls from outside the list are
        // rejected with 403. Empty disables IP gating.
        // Example: '10.0.0.0/8,172.16.0.0/12'
        'allow_ips' => env('METRICS_ALLOW_IPS', ''),

        // Phase-14 OBSERV-9: histogram bucket boundaries in
        // milliseconds. Exponential progression is the Prometheus
        // default and captures typical web latencies (sub-5ms cache
        // hits through 2.5s timeouts).
        'histogram_buckets_ms' => [5, 10, 25, 50, 100, 250, 500, 1000, 2500],
    ],

    'sentry' => [
        // Phase-14 OBSERV-2: deploy.sh writes this when stamping
        // SENTRY_RELEASE; config/sentry.php reads the same env var.
        // Kept here for discoverability — sentry.php is the
        // canonical consumer.
        'release_env_var' => 'SENTRY_RELEASE',
    ],

    'csp' => [
        // Phase-15 FRONT-6: where SecurityHeaders sends report-uri.
        // Override with CSP_REPORT_URI to point at an external
        // collector (e.g. report-uri.com).
        'report_uri' => env('CSP_REPORT_URI', '/api/v1/csp-reports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SLO budgets — Phase-22 PERF-SLO-2
    |--------------------------------------------------------------------------
    |
    | Machine-readable service-level objectives. Phase-14's slo.md
    | described SLOs in prose; this is the enforceable definition the
    | tooling reads. The RecordRequestLatency middleware (PERF-SLO-1)
    | emits http_request_ms{route,method,status}; the slo:report
    | command (PERF-SLO-3) aggregates that histogram and compares each
    | route class against its p95 budget here. The same route-class
    | taxonomy is documented in docs/runbooks/slo.md — keep them in sync.
    |
    */
    'slo' => [
        // p95 latency budget per route class, in milliseconds. A route
        // is bucketed into a class by name (see the resolver in
        // App\Support\RouteClassResolver):
        //   webhook    — *.webhooks.* / webhook ingress (bursty, async-ish)
        //   write_path — *.store / *.update / *.destroy (mutations)
        //   report     — reports.* / exports (heavy aggregation)
        //   read_path  — everything else (the common navigation case)
        'latency_budgets_ms' => [
            'read_path' => (int) env('SLO_READ_P95_MS', 500),
            'write_path' => (int) env('SLO_WRITE_P95_MS', 1000),
            'webhook' => (int) env('SLO_WEBHOOK_P95_MS', 2000),
            'report' => (int) env('SLO_REPORT_P95_MS', 3000),
        ],

        // Global error-rate budget (fraction of 5xx responses). slo:report
        // flags the window as out-of-SLO above this.
        'error_rate_budget' => (float) env('SLO_ERROR_RATE_BUDGET', 0.01),

        // Default evaluation window for slo:report when --since is not
        // passed, in days of metric day-buckets.
        'evaluation_window_days' => (int) env('SLO_EVALUATION_WINDOW_DAYS', 1),
    ],

];
