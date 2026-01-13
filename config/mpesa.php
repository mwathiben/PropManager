<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live transactions.
    |
    */

    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

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
    | App Credentials
    |--------------------------------------------------------------------------
    |
    | Consumer key and secret from Safaricom developer portal.
    |
    */

    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | STK Push (Lipa Na M-Pesa Online)
    |--------------------------------------------------------------------------
    */

    'stk' => [
        'shortcode' => env('MPESA_STK_SHORTCODE'),
        'passkey' => env('MPESA_STK_PASSKEY'),
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
    */

    'c2b' => [
        'shortcode' => env('MPESA_C2B_SHORTCODE'),
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
    */

    'till' => [
        'shortcode' => env('MPESA_TILL_NUMBER'),
        'validation_url' => env('MPESA_TILL_VALIDATION_URL'),
        'confirmation_url' => env('MPESA_TILL_CONFIRMATION_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | B2C (Business to Customer) - For refunds
    |--------------------------------------------------------------------------
    */

    'b2c' => [
        'shortcode' => env('MPESA_B2C_SHORTCODE'),
        'initiator_name' => env('MPESA_B2C_INITIATOR'),
        'initiator_password' => env('MPESA_B2C_PASSWORD'),
        'security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
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
    */

    'defaults' => [
        'party_b' => env('MPESA_PARTY_B'), // Usually same as shortcode
        'account_reference_prefix' => env('MPESA_ACCOUNT_PREFIX', 'PROP'),
        'currency' => 'KES',
    ],

];
