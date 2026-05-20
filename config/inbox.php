<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Phase-63 INBOX-MOD-2: Retention
    |--------------------------------------------------------------------------
    |
    | Default Kenya DPA retention window for landlord<->tenant
    | communications: 7 years. Per-landlord override lives on
    | users.message_retention_days (nullable; NULL = use this default).
    */
    'retention' => [
        'default_days' => env('INBOX_RETENTION_DAYS', 2557),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase-63 INBOX-MOD-3: Rate limit + body cap + content policy
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_minute' => env('INBOX_RATE_LIMIT_PER_MINUTE', 20),
    ],

    'body_max_length' => 4000,

    'content' => [
        // Operator-curated list. Empty by default — the URL-repetition
        // and non-printable-character heuristics catch the obvious
        // spam patterns without needing a curated list.
        'spam_tokens' => [],

        // Phase-63 INBOX-MOD-3: heuristic thresholds.
        'url_repetition_threshold' => 5,
        'non_printable_fraction_threshold' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase-67 ATTACHMENT-SCAN: malware scanning for inbox attachments
    |--------------------------------------------------------------------------
    |
    | driver: 'null' (default, no-op — local/test) | 'clamav' (clamd
    | INSTREAM) | 'fake' (EICAR detector, tests/staging). When the scanner
    | errors (e.g. clamd down), fail_closed=true rejects the upload (secure
    | default); set false to persist with scan_status=error instead.
    */
    'scan' => [
        'driver' => env('INBOX_SCAN_DRIVER', 'null'),
        'socket' => env('INBOX_SCAN_SOCKET'),
        'host' => env('INBOX_SCAN_HOST', '127.0.0.1'),
        'port' => (int) env('INBOX_SCAN_PORT', 3310),
        'timeout' => (int) env('INBOX_SCAN_TIMEOUT', 10),
        'fail_closed' => (bool) env('INBOX_SCAN_FAIL_CLOSED', true),
    ],
];
