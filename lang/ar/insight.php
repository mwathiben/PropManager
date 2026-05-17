<?php

declare(strict_types=1);

return [
    'ops_dashboard' => [
        'heading' => '[TODO-ar] Operations dashboard',
        'mrr_total_label' => '[TODO-ar] MRR (today)',
        'churn_label' => '[TODO-ar] Monthly churn rate',
        'incidents_label' => '[TODO-ar] Active incidents',
        'alerts_label' => '[TODO-ar] Alerts fired (24h)',
        'dependency_label' => '[TODO-ar] Dependency health',
        'nav_links' => '[TODO-ar] Drill-downs',
    ],
    'landlord_growth' => [
        'engagement_card_heading' => '[TODO-ar] Engagement score',
        'referrals_card_heading' => '[TODO-ar] Referrals (30d)',
        'usage_card_heading' => '[TODO-ar] Plan usage',
        'deep_dive_link' => '[TODO-ar] View details',
    ],
    'api' => [
        'engagement_endpoint_description' => '[TODO-ar] Per-landlord daily engagement scores with sub-score components.',
        'summary_endpoint_description' => '[TODO-ar] Aggregate landlord insight summary (engagement + usage + referrals + MRR contribution).',
    ],
    'exports' => [
        'mrr_filename_prefix' => '[TODO-ar] mrr-snapshot',
        'engagement_filename_prefix' => '[TODO-ar] engagement',
        'product_events_filename_prefix' => '[TODO-ar] product-events',
    ],
    'cron_budget' => [
        'warning_heading' => '[TODO-ar] Daily cron runtime exceeds budget',
        'total_minutes_label' => '[TODO-ar] Total runtime (24h)',
        'per_command_label' => '[TODO-ar] Per-command runtime',
    ],
];
