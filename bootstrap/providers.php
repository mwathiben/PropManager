<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // Phase-15 PERF-6: DB::listen wiring for slow-query logging.
    // No-op unless SLOW_QUERY_THRESHOLD_MS env is set.
    App\Providers\SlowQueryServiceProvider::class,
];
