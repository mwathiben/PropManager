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

    // HTTP retry/timeout: see config/payments.php 'gateways.intasend'

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
    | Optional IP allowlist (defense-in-depth ON TOP of the challenge, which
    | is the primary control). Empty by default — IntaSend's documented model
    | is challenge-based, and IntaSend does not publish a stable webhook-source
    | IP range, so failing closed on an empty list would 403 every callback and
    | halt payments. Set INTASEND_WEBHOOK_ALLOWED_IPS (comma-separated) to
    | enforce; when set, ValidateIntaSendWebhook rejects any other source IP.
    */
    'webhook_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTASEND_WEBHOOK_ALLOWED_IPS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Platform Fee Settings
    |--------------------------------------------------------------------------
    |
    | PropManager platform fee configuration for split payments.
    | This is the platform's wallet for collecting fees from all landlords.
    |
    */
    'platform_fee_percentage' => 2.5,
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
