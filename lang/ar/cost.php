<?php

declare(strict_types=1);

return [
    'dashboard' => [
        'landlord_cost_heading' => '[TODO-ar] Landlord cost attribution',
        'top_landlords' => '[TODO-ar] Top landlords by cost',
        'days_window' => '[TODO-ar] Days window',
    ],
    'storage' => [
        'tier_standard' => '[TODO-ar] Standard (hot)',
        'tier_ia' => '[TODO-ar] Infrequent Access (warm)',
        'tier_glacier' => '[TODO-ar] Glacier (cold)',
        'tier_deep_archive' => '[TODO-ar] Glacier Deep Archive (frozen)',
        'bytes_label' => '[TODO-ar] Bytes',
        'files_label' => '[TODO-ar] Files',
    ],
    'query' => [
        'scan_to_return_ratio' => '[TODO-ar] Scan-to-return ratio',
        'p50_label' => '[TODO-ar] P50',
        'p90_label' => '[TODO-ar] P90',
    ],
    'cache' => [
        'hit_rate_label' => '[TODO-ar] Hit rate',
        'low_hit_rate_warning' => '[TODO-ar] Cache hit rate below configured threshold.',
    ],
    'logs' => [
        'volume_label' => '[TODO-ar] Log volume (24h)',
        'median_label' => '[TODO-ar] Median',
        'p95_label' => '[TODO-ar] P95',
    ],
];
