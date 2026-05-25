<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin system settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'System Settings',
    'subtitle' => 'Configure payment gateway for subscription payments',
    'back_to_dashboard' => 'Back to Dashboard',
    'email_sms' => [
        'heading' => 'Email and SMS Configuration',
        'intro' => 'Email and SMS provider settings are now configured in the',
        'notification_center' => 'Notification Center',
        'location' => 'under Operations > Notifications > Settings.',
    ],
    'gateway' => [
        'title' => 'Payment Gateway (Paystack)',
        'subtitle' => 'Configure Paystack for subscription payments',
        'configured' => 'Configured',
        'not_configured' => 'Not Configured',
    ],
    'form' => [
        'public_key' => 'Public Key',
        'public_key_hint' => 'Leave blank to keep current key',
        'secret_key' => 'Secret Key',
    ],
    'actions' => [
        'testing' => 'Testing...',
        'test_connection' => 'Test Connection',
        'saving' => 'Saving...',
        'save' => 'Save Changes',
    ],
    'errors' => [
        'secret_key_required' => 'Please enter your secret key first',
        'connection_failed' => 'Connection failed: {message}',
    ],
];
