<?php

declare(strict_types=1);

/**
 * Phase-39 VENDOR-ANALYTICS-3: per-vendor config + enable flags.
 *
 * Default everything DISABLED — a fresh install never accidentally
 * pipes customer events to a third party. Production operators
 * opt in by setting VENDORS_*_ENABLED + supplying the credential
 * env vars. Phase 40 [STRIPE-GATEWAY] will add its own block here.
 */
return [
    'posthog' => [
        'enabled' => env('VENDORS_POSTHOG_ENABLED', false),
        'api_key' => env('VENDORS_POSTHOG_API_KEY'),
        'host' => env('VENDORS_POSTHOG_HOST', 'https://app.posthog.com'),
        'sample_rate' => (float) env('VENDORS_POSTHOG_SAMPLE_RATE', 1.0),
    ],
];
