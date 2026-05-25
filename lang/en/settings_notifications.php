<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: account-settings notifications tab. Mirror en/sw/ar.
 */
return [
    'header' => [
        'title' => 'Notification Defaults',
        'subtitle' => 'Set default notification preferences for new tenants. Individual tenants can override these.',
        'notification_center' => 'Notification Center',
    ],
    'channels' => [
        'heading' => 'Default Channels',
        'prompt' => 'Which channels should be enabled by default for new tenants?',
        'primary' => 'Primary',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'email' => 'Email',
    ],
    'types' => [
        'heading' => 'Notification Types',
        'prompt' => 'Which notification types should be enabled by default?',
        'rent_reminder' => [
            'label' => 'Rent Reminders',
            'description' => 'Remind tenants before rent is due',
        ],
        'arrears_notice' => [
            'label' => 'Arrears Notices',
            'description' => 'Notify tenants of outstanding balances',
        ],
        'invoice' => [
            'label' => 'Invoice Notifications',
            'description' => 'Send invoice details to tenants',
        ],
        'receipt' => [
            'label' => 'Payment Receipts',
            'description' => 'Confirm payments received',
        ],
        'rent_hike' => [
            'label' => 'Rent Adjustments',
            'description' => 'Notify tenants of rent changes',
        ],
        'lease_expiry' => [
            'label' => 'Lease Expiry',
            'description' => 'Alert tenants when lease is ending',
        ],
        'maintenance_notice' => [
            'label' => 'Maintenance Notices',
            'description' => 'Property maintenance updates',
        ],
        'general' => [
            'label' => 'General Updates',
            'description' => 'Other important communications',
        ],
    ],
    'timing' => [
        'heading' => 'Timing',
        'days_before_label' => 'Send rent reminders how many days before due?',
        'days_before_suffix' => 'days before',
    ],
    'info' => [
        'title' => 'These are default settings',
        'body_lead' => 'Individual tenants can customize their own notification preferences. For advanced settings like templates and schedules, visit the',
        'notification_center' => 'Notification Center',
    ],
    'save' => 'Save Notification Defaults',
    'saving' => 'Saving...',
];
