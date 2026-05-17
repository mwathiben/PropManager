<?php

declare(strict_types=1);

return [
    'gateways' => [
        'paystack_label' => '[TODO-ar] Paystack (KES domestic)',
        'stripe_label' => '[TODO-ar] Stripe (USD/EUR/GBP)',
        'mpesa_label' => '[TODO-ar] M-Pesa',
        'auto_label' => '[TODO-ar] auto (route by currency)',
    ],
    'preference' => [
        'heading' => '[TODO-ar] Payment gateway preference',
        'helper' => '[TODO-ar] Choose a forced gateway, or "auto" to let the system pick (Paystack for KES, Stripe otherwise).',
        'updated_flash' => '[TODO-ar] Preference updated.',
        'invalid_value' => '[TODO-ar] Choose paystack, stripe, or auto.',
    ],
    'reconcile' => [
        'drift_alert_heading' => '[TODO-ar] Gateway ledger drift',
        'drift_alert_helper' => '[TODO-ar] Run payments:gateway-reconcile and inspect the per-landlord drift count.',
    ],
    'webhook' => [
        'invalid_signature' => '[TODO-ar] Webhook rejected — signature mismatch.',
        'not_configured' => '[TODO-ar] Webhook rejected — secret not configured.',
        'duplicate_event' => '[TODO-ar] Duplicate event — already processed.',
    ],
    'currency' => [
        'kes_to_paystack_hint' => '[TODO-ar] KES payments route to Paystack.',
        'usd_to_stripe_hint' => '[TODO-ar] USD/EUR/GBP payments route to Stripe.',
    ],
    'payout' => [
        'balance_audit_heading' => '[TODO-ar] Stripe payout health',
        'payout_failure_alert' => '[TODO-ar] Stripe payout failed — :reason',
        'balance_threshold_label' => '[TODO-ar] Failure threshold (24h)',
        'incident_summary_template' => '[TODO-ar] :count payouts failed in the last 24 hours for landlord :landlord_id.',
    ],
    'methods' => [
        'saved_cards_heading' => '[TODO-ar] Saved payment methods',
        'add_card_button' => '[TODO-ar] Add card',
        'last4_label' => '[TODO-ar] Card ending in :last4',
        'brand_label' => '[TODO-ar] Card type',
        'default_badge' => '[TODO-ar] Default',
        'remove_card_button' => '[TODO-ar] Remove card',
        'setup_intent_failed' => '[TODO-ar] Could not initialise card setup — please retry.',
        'card_attached_flash' => '[TODO-ar] Card saved.',
        'card_removed_flash' => '[TODO-ar] Card removed.',
    ],
    'cart' => [
        'heading' => '[TODO-ar] Checkout cart',
        'total_label' => '[TODO-ar] Total',
        'currency_section_heading' => '[TODO-ar] Pay in :currency',
        'confirm_payment_button' => '[TODO-ar] Confirm payment',
        'payment_succeeded_message' => '[TODO-ar] Payment succeeded.',
        'payment_failed_message' => '[TODO-ar] Payment failed. Please retry.',
        'expired_session_message' => '[TODO-ar] This checkout session has expired or already completed.',
        'line_description' => '[TODO-ar] Cart session :session — :currency group',
        'unsupported_currency_pair' => '[TODO-ar] No gateway available for currency :currency.',
    ],
    'plan_sync' => [
        'drift_mode_label' => '[TODO-ar] Drift resolution mode',
        'manual_review_option' => '[TODO-ar] Manual review',
        'always_app_wins_option' => '[TODO-ar] Always app wins',
        'always_stripe_wins_option' => '[TODO-ar] Always Stripe wins',
        'drift_history_heading' => '[TODO-ar] Recent drift events',
        'drift_resolved_badge' => '[TODO-ar] Resolved',
        'drift_pending_badge' => '[TODO-ar] Pending',
        'drift_manual_pending_badge' => '[TODO-ar] Awaiting manual review',
        'drift_mode_updated_flash' => '[TODO-ar] Drift resolution mode for :plan updated.',
    ],
    'tax' => [
        'vat_heading' => '[TODO-ar] VAT & tax registration',
        'vat_label' => '[TODO-ar] VAT',
        'vat_rate_label' => '[TODO-ar] VAT rate',
        'vat_amount_label' => '[TODO-ar] VAT amount',
        'kra_pin_label' => '[TODO-ar] KRA PIN',
        'kra_pin_placeholder' => '[TODO-ar] e.g. A001234567Z',
        'vat_rate_override_label' => '[TODO-ar] VAT rate override (basis points)',
        'stripe_tax_enabled_label' => '[TODO-ar] Enable Stripe automatic tax for non-KES charges',
        'vat_inclusive_disclaimer' => '[TODO-ar] Invoice totals include 16% Kenya VAT where applicable.',
        'kra_pin_missing_warning' => '[TODO-ar] KRA PIN is not set — VAT-registered landlords must provide one before invoicing.',
        'kra_pin_invalid_format' => '[TODO-ar] KRA PIN must match the format A### or P###. Example: A001234567Z.',
        'updated_flash' => '[TODO-ar] Tax configuration for :name updated.',
    ],
];
