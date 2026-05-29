<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: super-admin platform billing settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Platform Billing Settings',
    'stats' => [
        'monthly_revenue' => 'This Month Revenue',
        'transactions' => 'Transactions',
        'avg_fee_percent' => 'Avg Fee %',
        'total_processed' => 'Total Processed',
    ],
    'tabs' => [
        'settings' => 'Settings',
        'history' => 'Change History',
    ],
    'billing_model' => [
        'heading' => 'Billing Model',
        'current_label' => 'Current:',
        'select_label' => 'Select Model',
        'reason_label' => 'Reason (optional)',
        'reason_placeholder' => 'Why are you changing the billing model?',
        'submit' => 'Update Billing Model',
        'submitting' => 'Updating...',
    ],
    'calculator' => [
        'heading' => 'Fee Calculator',
        'amount_placeholder' => 'Enter amount',
        'calculate' => 'Calculate',
        'gross_amount' => 'Gross Amount:',
        'platform_fee' => 'Platform Fee ({percent}%):',
        'landlord_receives' => 'Landlord Receives:',
    ],
    'fees' => [
        'heading' => 'Fee Configuration',
        'transaction_fee_percent' => 'Transaction Fee %',
        'transaction_fee_hint' => 'Percentage charged per transaction',
        'minimum_fee' => 'Minimum Fee ({currency})',
        'maximum_fee' => 'Maximum Fee ({currency})',
        'maximum_fee_placeholder' => 'No cap',
        'fee_bearer' => 'Fee Bearer',
        'hybrid_discount' => 'Hybrid Subscriber Discount %',
        'hybrid_discount_hint' => 'Fee reduction for subscribers (100 = no fees)',
        'reason_label' => 'Reason (optional)',
        'reason_placeholder' => 'Why are you changing the fees?',
        'submit' => 'Save Fee Settings',
        'submitting' => 'Saving...',
    ],
    'history' => [
        'heading' => 'Recent Changes',
        'reason_prefix' => 'Reason: {reason}',
        'empty' => 'No changes recorded yet',
    ],
];
