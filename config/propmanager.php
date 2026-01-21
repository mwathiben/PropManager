<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Water Billing Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for water billing when landlord hasn't configured
    | their own rates. These serve as the system-wide fallback.
    |
    */

    'water' => [
        'default_rate' => env('WATER_DEFAULT_RATE', 150),
    ],

];
