<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IntaSend API Endpoints
    |--------------------------------------------------------------------------
    |
    | Official IntaSend API base URLs.
    | Per-landlord credentials are stored in payment_configurations table,
    | NOT in environment variables.
    |
    | Docs: https://developers.intasend.com/docs
    |
    */
    'endpoints' => [
        'sandbox' => 'https://sandbox.intasend.com',
        'production' => 'https://payment.intasend.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Timeout and retry settings for API calls.
    | Consistent with Paystack/M-Pesa patterns in this codebase.
    |
    */
    'timeout' => 30,
    'retry_times' => 3,
    'retry_sleep' => 100,

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | IntaSend uses CHALLENGE-BASED verification (not HMAC).
    | Each landlord sets their own challenge in their IntaSend dashboard.
    | The challenge is stored in payment_configurations.intasend_webhook_challenge.
    |
    */
    'webhook_callback_path' => '/webhooks/intasend/mpesa',

    /*
    |--------------------------------------------------------------------------
    | Platform Fee Settings
    |--------------------------------------------------------------------------
    |
    | PropManager platform fee configuration for split payments.
    | This is the platform's wallet for collecting fees from all landlords.
    |
    */
    'platform_fee_percentage' => env('INTASEND_PLATFORM_FEE_PERCENTAGE', 2.5),
    'platform_wallet_id' => env('INTASEND_PLATFORM_WALLET_ID'),

    /*
    |--------------------------------------------------------------------------
    | Transaction States
    |--------------------------------------------------------------------------
    |
    | IntaSend webhook payload states:
    | - PENDING: Transaction logged, no action taken
    | - PROCESSING: Customer actively making payment
    | - COMPLETE: Successful transaction
    | - FAILED: Declined (check failed_reason field)
    |
    */
    'states' => [
        'pending' => 'PENDING',
        'processing' => 'PROCESSING',
        'complete' => 'COMPLETE',
        'failed' => 'FAILED',
    ],
];
