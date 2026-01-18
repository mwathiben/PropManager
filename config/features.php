<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control the activation of new features during gradual rollouts.
    | Set to true to enable a feature, false to use the legacy implementation.
    |
    */

    'notification_v2' => env('FEATURE_NOTIFICATION_V2', true),
];
