<?php

declare(strict_types=1);

return [
    'gateways' => [
        'paystack_label' => 'Paystack (KES domestic)',
        'stripe_label' => 'Stripe (USD/EUR/GBP)',
        'mpesa_label' => 'M-Pesa',
        'auto_label' => 'auto (route by currency)',
    ],
    'preference' => [
        'heading' => 'Payment gateway preference',
        'helper' => 'Choose a forced gateway, or "auto" to let the system pick (Paystack for KES, Stripe otherwise).',
        'updated_flash' => 'Preference updated.',
        'invalid_value' => 'Choose paystack, stripe, or auto.',
    ],
    'reconcile' => [
        'drift_alert_heading' => 'Gateway ledger drift',
        'drift_alert_helper' => 'Run payments:gateway-reconcile and inspect the per-landlord drift count.',
    ],
    'webhook' => [
        'invalid_signature' => 'Webhook rejected — signature mismatch.',
        'not_configured' => 'Webhook rejected — secret not configured.',
        'duplicate_event' => 'Duplicate event — already processed.',
    ],
    'currency' => [
        'kes_to_paystack_hint' => 'KES payments route to Paystack.',
        'usd_to_stripe_hint' => 'USD/EUR/GBP payments route to Stripe.',
    ],
];
