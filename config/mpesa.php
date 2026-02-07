<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Default Environment
    |--------------------------------------------------------------------------
    |
    | Per-landlord environment is stored in payment_configurations.mpesa_environment.
    | MpesaService::withConfig() overrides this per-landlord at runtime.
    | This default only applies when no PaymentConfiguration is loaded.
    |
    */

    'environment' => 'sandbox',

    /*
    |--------------------------------------------------------------------------
    | M-Pesa API Endpoints
    |--------------------------------------------------------------------------
    */

    'endpoints' => [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ],

    /*
    |--------------------------------------------------------------------------
    | App Credentials - REMOVED (Security)
    |--------------------------------------------------------------------------
    |
    | Per-tenant M-Pesa credentials must come from PaymentConfiguration
    | (database), not .env. This prevents credential leakage in multi-tenant
    | SaaS. Configure via Settings > Payment Methods.
    |
    | NEVER add consumer_key or consumer_secret here.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | STK Push (Lipa Na M-Pesa Online)
    |--------------------------------------------------------------------------
    |
    | shortcode and passkey are per-tenant - stored in PaymentConfiguration.
    | callback_url is platform-level - shared webhook endpoint.
    |
    */

    'stk' => [
        'callback_url' => env('MPESA_STK_CALLBACK_URL'),
        'transaction_type' => env('MPESA_STK_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
    ],

    /*
    |--------------------------------------------------------------------------
    | C2B Paybill (Customer to Business with Account Number)
    |--------------------------------------------------------------------------
    |
    | Paybill requires an account number (BillRefNumber) for matching payments
    | to invoices. Used for recurring billing where tenant enters invoice number.
    |
    | NOTE: Shortcode is per-tenant - stored in PaymentConfiguration.
    | Only platform-level webhook URLs are configured here.
    |
    */

    'c2b' => [
        'validation_url' => env('MPESA_C2B_VALIDATION_URL'),
        'confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Till / Buy Goods (Customer to Business without Account Number)
    |--------------------------------------------------------------------------
    |
    | Till payments do not include an account reference. Matching is done via
    | sender phone number to tenant records. Unmatched payments are queued
    | for manual reconciliation.
    |
    | NOTE: Shortcode is per-tenant - stored in PaymentConfiguration.
    | Only platform-level webhook URLs are configured here.
    |
    */

    'till' => [
        'validation_url' => env('MPESA_TILL_VALIDATION_URL'),
        'confirmation_url' => env('MPESA_TILL_CONFIRMATION_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | B2C (Business to Customer) - For refunds
    |--------------------------------------------------------------------------
    |
    | All B2C credentials are per-tenant - stored in PaymentConfiguration.
    | Only platform-level webhook URLs are configured here.
    |
    */

    'b2c' => [
        'result_url' => env('MPESA_B2C_RESULT_URL'),
        'timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    |
    | Safaricom IPs that are allowed to call webhooks.
    | Set to empty array to disable IP validation (not recommended).
    |
    */

    'allowed_ips' => array_filter(explode(',', env('MPESA_ALLOWED_IPS', '196.201.214.200,196.201.214.206,196.201.213.114,196.201.214.207,196.201.214.208,196.201.213.44,196.201.212.127,196.201.212.138,196.201.212.129,196.201.212.136,196.201.212.74,196.201.212.69'))),

    /*
    |--------------------------------------------------------------------------
    | Transaction Defaults
    |--------------------------------------------------------------------------
    |
    | NOTE: party_b (usually shortcode) is per-tenant - use PaymentConfiguration.
    |
    */

    'defaults' => [
        'account_reference_prefix' => env('MPESA_ACCOUNT_PREFIX', 'PROP'),
        'currency' => 'KES',
    ],

];
