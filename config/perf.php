<?php

declare(strict_types=1);

/**
 * Phase-22 PERF-SLO + Phase-27 BI-CI-2: performance budget config.
 *
 * Budgets are the gate that flips a perf regression from "invisible"
 * to "build fails". Each report type has its own ms budget because
 * a 5-year cohort analysis is genuinely slower than a flat
 * dashboard read — one global ceiling would either be too loose for
 * dashboards or too tight for cohorts.
 *
 * Re-baselining: edit the value here with a one-line commit message
 * explaining why. The Phase27PerfTest watchdog measures median over
 * 5 runs and fails when median > budget.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Report query budget (Phase-27 BI-CI-2)
    |--------------------------------------------------------------------------
    | Median ms per report endpoint over 5 runs against a seeded
    | fixture. Initial budgets are generous (5-10x the local-dev
    | measurement) to absorb CI single-threaded php-artisan-serve
    | variance. Tighten over time as the metric stabilises.
    */
    'report_query_budget_ms' => [
        'financial' => 1500,
        'occupancy' => 1500,
        'arrears' => 1500,
        'water' => 2000,
        'cohort' => 2500,
        'noi' => 2000,
        'forecast' => 2500,
        'builder_custom' => 3000,
    ],
];
