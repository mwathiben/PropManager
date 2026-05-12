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

];
