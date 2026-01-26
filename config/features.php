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

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Payment Links (COM-021)
    |--------------------------------------------------------------------------
    |
    | Controls whether payment links are included in WhatsApp template variables
    | for rent_reminder and arrears_notice messages.
    |
    | Keep DISABLED until Meta approves updated templates with payment_link var.
    | When disabled, WhatsApp uses plain text fallback; payment links still sent
    | via SMS fallback channel.
    |
    | See: dashboard-communication-prd.json COM-021
    |
    */
    'whatsapp_payment_links_enabled' => env('WHATSAPP_PAYMENT_LINKS_ENABLED', false),
];
