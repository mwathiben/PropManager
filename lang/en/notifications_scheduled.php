<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: scheduled-notifications tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Scheduled Notifications',
    'subheading' => 'Automate rent reminders, arrears notices, and lease expiry alerts',
    'create_schedule' => 'Create Schedule',
    'status' => [
        'active' => 'Active',
        'paused' => 'Paused',
    ],
    'field' => [
        'type' => 'Type',
        'trigger' => 'Trigger',
        'send_time' => 'Send Time',
        'channels' => 'Channels',
    ],
    'next' => 'Next: {value}',
    'last' => 'Last: {value}',
    'action' => [
        'run_now' => 'Run Now',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'cancel' => 'Cancel',
    ],
    'empty' => [
        'title' => 'No Schedules Yet',
        'body' => 'Create automated notification schedules to keep tenants informed',
    ],
    'modal' => [
        'edit_title' => 'Edit Schedule',
        'create_title' => 'Create Schedule',
        'update' => 'Update Schedule',
        'create' => 'Create Schedule',
    ],
    'form' => [
        'name' => 'Schedule Name',
        'name_placeholder' => 'e.g., 3-Day Rent Reminder',
        'notification_type' => 'Notification Type',
        'template' => 'Template (Optional)',
        'use_default' => 'Use default',
        'trigger' => 'Trigger',
        'days' => 'Days',
        'send_time' => 'Send Time',
        'channels' => 'Channels',
        'is_active' => 'Schedule is active',
    ],
    'trigger_type' => [
        'days_before_due' => [
            'label' => 'Days Before Rent Due',
            'description' => 'Send X days before the rent due date',
        ],
        'days_after_overdue' => [
            'label' => 'Days After Overdue',
            'description' => 'Send X days after rent becomes overdue',
        ],
        'days_before_expiry' => [
            'label' => 'Days Before Lease Expiry',
            'description' => 'Send X days before lease expires',
        ],
    ],
    'notification_type' => [
        'rent_reminder' => 'Rent Reminder',
        'arrears_notice' => 'Arrears Notice',
        'lease_expiry' => 'Lease Expiry',
    ],
    'channel' => [
        'email' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Push',
    ],
    'next_run' => [
        'paused' => 'Paused',
        'calculating' => 'Calculating...',
    ],
    'last_run' => [
        'never' => 'Never',
    ],
    'confirm' => [
        'delete' => 'Are you sure you want to delete "{name}"?',
        'run' => 'Run "{name}" now? This will send notifications to all matching tenants.',
    ],
];
