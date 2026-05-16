<?php

declare(strict_types=1);

return [
    'ops_dashboard' => [
        'heading' => 'Operations dashboard',
        'mrr_total_label' => 'MRR (today)',
        'churn_label' => 'Monthly churn rate',
        'incidents_label' => 'Active incidents',
        'alerts_label' => 'Alerts fired (24h)',
        'dependency_label' => 'Dependency health',
        'nav_links' => 'Drill-downs',
    ],
    'landlord_growth' => [
        'engagement_card_heading' => 'Engagement score',
        'referrals_card_heading' => 'Referrals (30d)',
        'usage_card_heading' => 'Plan usage',
        'deep_dive_link' => 'View details',
    ],
    'api' => [
        'engagement_endpoint_description' => 'Per-landlord daily engagement scores with sub-score components.',
        'summary_endpoint_description' => 'Aggregate landlord insight summary (engagement + usage + referrals + MRR contribution).',
    ],
    'exports' => [
        'mrr_filename_prefix' => 'mrr-snapshot',
        'engagement_filename_prefix' => 'engagement',
        'product_events_filename_prefix' => 'product-events',
    ],
    'cron_budget' => [
        'warning_heading' => 'Daily cron runtime exceeds budget',
        'total_minutes_label' => 'Total runtime (24h)',
        'per_command_label' => 'Per-command runtime',
    ],
];
