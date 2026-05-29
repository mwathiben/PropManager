<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\SlowQueryServiceProvider::class,
    // App\Providers\TelescopeServiceProvider is registered conditionally
    // in AppServiceProvider::register() (local-only). It must NOT be
    // listed here: telescope is a require-dev package, so `composer
    // install --no-dev` (production + the PERF-9 config:cache gate) has
    // no Laravel\Telescope\* classes, and an unconditional reference here
    // fatals config:cache. See https://laravel.com/docs/telescope#local-only-installation
];
