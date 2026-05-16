<?php

declare(strict_types=1);

return [
    'metrics' => [
        'driver_unavailable_heading' => 'Metrics driver unavailable',
        'install_phpredis_helper' => 'Install phpredis for production via `pecl install redis`.',
        'install_predis_helper' => 'Install predis for dev via `composer require predis/predis --dev`.',
    ],
    'bundle' => [
        'stale_warning_heading' => 'Frontend bundle is stale',
        'last_built_label' => 'Last built',
        'rebuild_cta' => 'Rebuild',
    ],
    'route_cache' => [
        'cache_compile_failed_heading' => 'Route cache failed to compile',
        'run_artisan_route_clear_helper' => 'Run `php artisan route:clear` and inspect duplicate names.',
    ],
    'test_health' => [
        'failure_baseline_label' => 'Test failure baseline',
        'ratchet_violation_helper' => 'New test failures violate the Phase-38 ratchet. Fix them or raise the baseline only for legitimate xfail.',
    ],
];
