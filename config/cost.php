<?php

declare(strict_types=1);

/**
 * Phase-33 COST-ATTRIB-2: per-unit Kenya-market prices used by
 * cost:attribute + storage:cost-audit to convert raw usage counters
 * into estimated monthly KES. Calibrate quarterly against actual AWS +
 * SMS + cron-server invoices.
 *
 * All rates in KES (Kenyan Shillings). USD-denominated AWS prices are
 * converted at the quarter's average rate (committed in repo when
 * recalibrated, NOT pulled dynamically).
 */

return [
    // Quarterly calibration timestamp + exchange rate used.
    'calibrated_at' => '2026-04-01',
    'usd_to_kes_rate' => 145.0,

    // Per-unit prices.
    'rates' => [
        // RDS / managed MySQL: ~$0.30 per million reads at db.t4g.medium.
        'kes_per_million_queries' => 43.5,
        // S3 STANDARD: $0.023/GB/month -> KES/GB/month.
        'kes_per_gb_s3_standard' => 3.34,
        // S3 STANDARD_IA: $0.0125/GB/month -> KES.
        'kes_per_gb_s3_ia' => 1.81,
        // S3 GLACIER: $0.004/GB/month -> KES.
        'kes_per_gb_s3_glacier' => 0.58,
        // S3 GLACIER_DEEP_ARCHIVE: $0.00099/GB/month -> KES.
        'kes_per_gb_s3_deep_archive' => 0.14,
        // Africa's Talking SMS: KES 0.80 per local SMS.
        'kes_per_sms' => 0.80,
        // Cron minute on a t3.small worker box: $0.00027/hour / 60.
        'kes_per_cron_minute' => 0.00065,
        // Sentry / Datadog log ingest: $1.50/GB -> KES/MB.
        'kes_per_mb_log' => 0.218,
    ],

    // High-volume landlord alert: fires when one landlord's metric
    // value exceeds this multiplier of the median for the same metric.
    'high_landlord_multiplier' => 5.0,
];
