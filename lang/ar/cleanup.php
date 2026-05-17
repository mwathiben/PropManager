<?php

declare(strict_types=1);

return [
    'metrics' => [
        'driver_unavailable_heading' => '[TODO-ar] Metrics driver unavailable',
        'install_phpredis_helper' => '[TODO-ar] Install phpredis for production via `pecl install redis`.',
        'install_predis_helper' => '[TODO-ar] Install predis for dev via `composer require predis/predis --dev`.',
    ],
    'bundle' => [
        'stale_warning_heading' => '[TODO-ar] Frontend bundle is stale',
        'last_built_label' => '[TODO-ar] Last built',
        'rebuild_cta' => '[TODO-ar] Rebuild',
    ],
    'route_cache' => [
        'cache_compile_failed_heading' => '[TODO-ar] Route cache failed to compile',
        'run_artisan_route_clear_helper' => '[TODO-ar] Run `php artisan route:clear` and inspect duplicate names.',
    ],
    'test_health' => [
        'failure_baseline_label' => '[TODO-ar] Test failure baseline',
        'ratchet_violation_helper' => '[TODO-ar] New test failures violate the Phase-38 ratchet. Fix them or raise the baseline only for legitimate xfail.',
    ],
];
