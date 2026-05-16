<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack - REMOVED (Security)
    |--------------------------------------------------------------------------
    |
    | Per-tenant Paystack credentials must come from PaymentConfiguration
    | (database), not .env. This prevents credential leakage in multi-tenant
    | SaaS. Configure via Settings > Payment Methods.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Stripe (system-wide webhook secret only)
    |--------------------------------------------------------------------------
    |
    | Phase-40 GATEWAY-WEBHOOK-1: per-tenant Stripe credentials live on
    | PaymentConfiguration (same pattern as Paystack). Only the SYSTEM-WIDE
    | webhook secret + publishable key for the PropManager SaaS billing
    | path live here — see docs/runbooks/payments.md for the boundary rule.
    |
    */
    'stripe' => [
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
    ],

];
