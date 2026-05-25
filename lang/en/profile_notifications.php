<?php

declare(strict_types=1);

/**
 * i18n migration: profile notifications (push-notification preferences) tab. Mirror en/sw/ar.
 */
return [
    'card' => [
        'title' => 'Push Notifications',
        'subtitle' => 'Receive instant updates on your device',
    ],
    'not_supported' => [
        'title' => 'Not Supported',
        'body' => 'Push notifications are not supported in this browser. Please use Chrome, Firefox, Edge, or Safari for push notifications.',
    ],
    'blocked' => [
        'title' => 'Notifications Blocked',
        'body' => 'Push notifications are blocked in your browser. To enable them:',
        'step_lock' => "Click the lock icon in your browser's address bar",
        'step_find' => 'Find "Notifications" in the site settings',
        'step_change' => 'Change from "Block" to "Allow"',
        'step_refresh' => 'Refresh this page',
    ],
    'status' => [
        'enabled' => 'Notifications enabled',
        'disabled' => 'Notifications disabled',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],
    'alerts' => [
        'intro' => 'Enable push notifications to receive instant alerts for:',
        'invoices' => 'New invoices and payment confirmations',
        'rent' => 'Rent reminders and due date alerts',
        'messages' => 'Important messages from your landlord',
        'maintenance' => 'Maintenance updates and announcements',
    ],
    'button' => [
        'enabling' => 'Enabling...',
        'enable' => 'Enable Push Notifications',
        'send_test' => 'Send Test',
        'disabling' => 'Disabling...',
        'disable' => 'Disable',
    ],
    'no_vapid' => 'Push notifications have not been configured yet. Please contact your property manager.',
    'devices' => [
        'title' => 'Multiple Devices',
        'body' => 'You can enable push notifications on multiple devices. Each device requires separate setup by logging in and enabling notifications on that device.',
    ],
    'test' => [
        'title' => 'Test Notification',
        'body' => 'Push notifications are working correctly!',
    ],
    'script' => [
        'permission_denied' => 'Push notification permission was denied. Please enable it in your browser settings.',
        'not_configured' => 'Push notifications are not configured. Please contact your landlord.',
        'enable_success' => 'Push notifications enabled successfully!',
        'enable_failed' => 'Failed to enable push notifications. Please try again.',
        'disable_success' => 'Push notifications disabled.',
        'disable_failed' => 'Failed to disable push notifications.',
    ],
];
