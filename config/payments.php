<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Security Configuration
    |--------------------------------------------------------------------------
    |
    | IP allowlists and validation settings for payment provider webhooks.
    | Provider IPs are public constants from official documentation — NOT env vars.
    |
    | M-Pesa IPs: Defined in config/mpesa.php (reads MPESA_ALLOWED_IPS env).
    | Paystack IPs: Hardcoded below from https://paystack.com/docs/payments/webhooks/
    |
    */

    'webhook_security' => [

        'mpesa' => [
            'timestamp_tolerance_minutes' => 15,
        ],

        'paystack' => [
            'allowed_ips' => [
                '52.31.139.75',
                '52.49.173.169',
                '52.214.14.220',
            ],
        ],

    ],

];
