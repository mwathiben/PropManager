<?php

declare(strict_types=1);

return [
    'metrics' => [
        'driver_unavailable_heading' => 'Kiendeshi cha metriki hakipatikani',
        'install_phpredis_helper' => 'Sakinisha phpredis kwa uzalishaji kupitia `pecl install redis`.',
        'install_predis_helper' => 'Sakinisha predis kwa maendeleo kupitia `composer require predis/predis --dev`.',
    ],
    'bundle' => [
        'stale_warning_heading' => 'Kifurushi cha mbele ni cha zamani',
        'last_built_label' => 'Iliyojengwa mwisho',
        'rebuild_cta' => 'Jenga upya',
    ],
    'route_cache' => [
        'cache_compile_failed_heading' => 'Kashe ya njia haikuweza kukusanywa',
        'run_artisan_route_clear_helper' => 'Endesha `php artisan route:clear` na chunguza majina yanayorudiwa.',
    ],
    'test_health' => [
        'failure_baseline_label' => 'Msingi wa kushindwa kwa majaribio',
        'ratchet_violation_helper' => 'Majaribio mapya yaliyoshindwa yanakiuka ratchet ya Awamu-38. Yarekebishe au panua msingi tu kwa xfail halali.',
    ],
];
