<?php

declare(strict_types=1);

/**
 * Phase-35 PLATFORM-* configuration.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Product analytics sample rate
    |--------------------------------------------------------------------------
    | Phase-35 PLATFORM-ANALYTICS-2: fraction (0.0-1.0) of successful
    | requests that fire a page_view product_event. At 0.1 we record
    | 10% of traffic — enough for funnel analysis, light enough not to
    | dominate DB write load. Set to 1.0 in development to see every
    | event; 0.0 disables tracking entirely.
    */
    'analytics_sample_rate' => env('PLATFORM_ANALYTICS_SAMPLE_RATE', 0.1),

    /*
    |--------------------------------------------------------------------------
    | Product events retention
    |--------------------------------------------------------------------------
    | Phase-37 PWA-RETENTION-STATS-1: product:prune deletes
    | product_events rows older than this many days. Default 180
    | (6 months) balances funnel-analysis utility against unbounded
    | row growth. Cold-storage rollover (PWA-RETENTION-STATS-2)
    | runs first so historical data isn't lost.
    */
    'product_events_retention_days' => env('PRODUCT_EVENTS_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Product events cold-storage rollover
    |--------------------------------------------------------------------------
    | Phase-37 PWA-RETENTION-STATS-2: product:cold-storage-rollover
    | writes events older than this many days to Storage::disk('archive')
    | as compressed JSONL before product:prune deletes them. 90 days
    | is the standard "warm vs cold" boundary for product analytics.
    */
    'product_events_cold_storage_days' => env('PRODUCT_EVENTS_COLD_STORAGE_DAYS', 90),
];
