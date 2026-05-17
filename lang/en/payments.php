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
    'plan_sync' => [
        'drift_mode_label' => 'Drift resolution mode',
        'manual_review_option' => 'Manual review',
        'always_app_wins_option' => 'Always app wins',
        'always_stripe_wins_option' => 'Always Stripe wins',
        'drift_history_heading' => 'Recent drift events',
        'drift_resolved_badge' => 'Resolved',
        'drift_pending_badge' => 'Pending',
        'drift_manual_pending_badge' => 'Awaiting manual review',
        'drift_mode_updated_flash' => 'Drift resolution mode for :plan updated.',
    ],
    'tax' => [
        'vat_heading' => 'VAT & tax registration',
        'vat_label' => 'VAT',
        'vat_rate_label' => 'VAT rate',
        'vat_amount_label' => 'VAT amount',
        'kra_pin_label' => 'KRA PIN',
        'kra_pin_placeholder' => 'e.g. A001234567Z',
        'vat_rate_override_label' => 'VAT rate override (basis points)',
        'stripe_tax_enabled_label' => 'Enable Stripe automatic tax for non-KES charges',
        'vat_inclusive_disclaimer' => 'Invoice totals include 16% Kenya VAT where applicable.',
        'kra_pin_missing_warning' => 'KRA PIN is not set — VAT-registered landlords must provide one before invoicing.',
        'kra_pin_invalid_format' => 'KRA PIN must match the format A### or P###. Example: A001234567Z.',
        'updated_flash' => 'Tax configuration for :name updated.',
    ],
];
