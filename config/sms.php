<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | Phase-45 EMERGENCY-CONTACT-SMS-1: which SmsDriver implementation
    | gets bound in the container. 'stub' (default) NEVER hits the
    | network — used in CI and dev. 'africastalking' uses the dominant
    | Kenya provider.
    |
    */
    'driver' => env('SMS_DRIVER', 'stub'),

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        'sender_id' => env('AFRICASTALKING_SENDER_ID'),
        'endpoint' => env('AFRICASTALKING_ENDPOINT', 'https://api.africastalking.com/version1/messaging'),
    ],
];
