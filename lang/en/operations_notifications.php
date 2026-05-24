<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub notifications tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'Complete Your Setup',
        'body' => 'Configure SMS, WhatsApp, or Push notifications to reach tenants through multiple channels.',
        'go_to_settings' => 'Go to Settings',
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
            'subtitle' => 'Send to a specific tenant',
        ],
        'bulk' => [
            'title' => 'Bulk Send',
            'subtitle' => 'Send to multiple tenants',
        ],
        'rent_reminders' => [
            'title' => 'Send Rent Reminders',
            'subtitle' => 'Notify all tenants about upcoming rent',
        ],
        'arrears_notices' => [
            'title' => 'Send Arrears Notices',
            'subtitle' => 'Notify tenants with outstanding balances',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'Channel Distribution',
        'empty' => 'No notifications sent yet',
    ],
    'recent_activity' => [
        'heading' => 'Recent Activity',
        'view_all' => 'View All →',
        'empty_title' => 'No notifications yet',
        'empty_subtitle' => 'Send your first notification to get started',
        'recipient' => 'To: {name}',
        'unknown_recipient' => 'Unknown',
    ],
    'full_center' => [
        'title' => 'Full Notification Center',
        'subtitle' => 'Manage templates, schedules, settings, and view complete history',
        'open' => 'Open Center',
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
    'confirm' => [
        'rent_reminders' => 'Send rent reminders to all tenants with active leases?',
        'arrears_notices' => 'Send arrears notices to all tenants with outstanding balances?',
    ],
];
