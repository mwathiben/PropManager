<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Phase-61 NOTICE-LIFECYCLE-1: notice-period thresholds (days)
    |--------------------------------------------------------------------------
    |
    | Minimum number of days between now() and the lifecycle event's
    | effective date. Kenya Landlord and Tenant Act baseline is 30
    | days for termination; transfer and pause are operator-chosen
    | defaults that landlords can override per-lease in a future cycle.
    |
    */

    'notice_periods' => [
        'termination' => (int) env('LEASE_NOTICE_TERMINATION_DAYS', 30),
        'transfer' => (int) env('LEASE_NOTICE_TRANSFER_DAYS', 14),
        'pause' => (int) env('LEASE_NOTICE_PAUSE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phase-61 RENEWAL-AUTO-2: auto-renew scan window
    |--------------------------------------------------------------------------
    |
    | lease:auto-renew cron scans leases whose end_date falls within
    | this many days of now(). 30 is the operator-visible window so
    | landlords have time to opt a lease out before the renewal fires.
    |
    */

    'auto_renew_scan_days_ahead' => (int) env('LEASE_AUTO_RENEW_SCAN_DAYS_AHEAD', 30),
];
