<?php

declare(strict_types=1);

return [
    'ops_dashboard' => [
        'heading' => 'Dashibodi ya uendeshaji',
        'mrr_total_label' => 'MRR (leo)',
        'churn_label' => 'Kiwango cha kuondoka kila mwezi',
        'incidents_label' => 'Matukio yanayoendelea',
        'alerts_label' => 'Tahadhari zilizotolewa (saa 24)',
        'dependency_label' => 'Afya ya utegemezi',
        'nav_links' => 'Tazama kwa undani',
    ],
    'landlord_growth' => [
        'engagement_card_heading' => 'Alama ya ushiriki',
        'referrals_card_heading' => 'Rufaa (siku 30)',
        'usage_card_heading' => 'Matumizi ya mpango',
        'deep_dive_link' => 'Tazama maelezo',
    ],
    'api' => [
        'engagement_endpoint_description' => 'Alama za ushiriki za kila siku kwa kila mwenye nyumba na vipengele vya alama ndogo.',
        'summary_endpoint_description' => 'Muhtasari wa jumla wa ufahamu wa mwenye nyumba (ushiriki + matumizi + rufaa + mchango wa MRR).',
    ],
    'exports' => [
        'mrr_filename_prefix' => 'mrr-snapshot',
        'engagement_filename_prefix' => 'engagement',
        'product_events_filename_prefix' => 'product-events',
    ],
    'cron_budget' => [
        'warning_heading' => 'Muda wa kila siku wa kazi za kiotomatiki umevuka bajeti',
        'total_minutes_label' => 'Jumla ya muda (saa 24)',
        'per_command_label' => 'Muda kwa kila amri',
    ],
];
