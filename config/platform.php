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
];
