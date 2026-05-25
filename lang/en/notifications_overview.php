<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications hub overview tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'Complete Your Setup',
        'body' => 'Configure SMS, WhatsApp, or Push notifications to reach tenants through multiple channels.',
        'run_wizard' => 'Run Setup Wizard',
    ],
    'stats' => [
        'total_sent' => 'Total Sent',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'this_month' => 'This Month',
    ],
    'quick_actions' => [
        'heading' => 'Quick Actions',
        'send' => [
            'title' => 'Send Notification',
            'description' => 'Send to a specific tenant',
        ],
        'bulk' => [
            'title' => 'Bulk Send',
            'description' => 'Send to multiple tenants',
        ],
        'rent_reminders' => [
            'title' => 'Send Rent Reminders',
            'description' => 'Notify all tenants about upcoming rent',
        ],
        'arrears_notices' => [
            'title' => 'Send Arrears Notices',
            'description' => 'Notify tenants with outstanding balances',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'Channel Distribution',
        'empty' => 'No notifications sent yet',
    ],
    'recent_activity' => [
        'heading' => 'Recent Activity',
        'empty_title' => 'No notifications yet',
        'empty_body' => 'Send your first notification to get started',
        'recipient' => 'To: {name}',
        'unknown_recipient' => 'Unknown',
    ],
    'confirm' => [
        'rent_reminders' => 'Send rent reminders to all tenants with active leases?',
        'arrears_notices' => 'Send arrears notices to all tenants with outstanding balances?',
    ],
    'types' => [
        'rent_reminder' => 'Rent Reminder',
        'arrears_notice' => 'Arrears Notice',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'rent_hike' => 'Rent Hike',
        'lease_expiry' => 'Lease Expiry',
        'general' => 'General',
    ],
    'channels' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Push',
    ],
];
