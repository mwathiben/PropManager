<?php

declare(strict_types=1);

return [
    'dashboard' => [
        'landlord_cost_heading' => 'Landlord cost attribution',
        'top_landlords' => 'Top landlords by cost',
        'days_window' => 'Days window',
    ],
    'storage' => [
        'tier_standard' => 'Standard (hot)',
        'tier_ia' => 'Infrequent Access (warm)',
        'tier_glacier' => 'Glacier (cold)',
        'tier_deep_archive' => 'Glacier Deep Archive (frozen)',
        'bytes_label' => 'Bytes',
        'files_label' => 'Files',
    ],
    'query' => [
        'scan_to_return_ratio' => 'Scan-to-return ratio',
        'p50_label' => 'P50',
        'p90_label' => 'P90',
    ],
    'cache' => [
        'hit_rate_label' => 'Hit rate',
        'low_hit_rate_warning' => 'Cache hit rate below configured threshold.',
    ],
    'logs' => [
        'volume_label' => 'Log volume (24h)',
        'median_label' => 'Median',
        'p95_label' => 'P95',
    ],
];
