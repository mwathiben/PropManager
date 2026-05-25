<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: push-notification opt-in prompt component. Mirror en/sw/ar.
 */
return [
    'success' => [
        'title' => 'Notifications enabled!',
        'body' => "You'll now receive instant updates about your rent and payments.",
    ],
    'error' => [
        'title' => "Couldn't enable notifications",
    ],
    'default' => [
        'title' => 'Stay Updated!',
        'body' => 'Enable push notifications to receive instant updates about rent reminders, payments, and important announcements.',
    ],
    'actions' => [
        'try_again' => 'Try Again',
        'dismiss' => 'Dismiss',
        'enabling' => 'Enabling...',
        'enable' => 'Enable Notifications',
        'maybe_later' => 'Maybe Later',
    ],
    'errors' => [
        'not_configured' => 'Push notifications are not configured by your landlord yet.',
        'failed' => 'Failed to enable notifications. Please try again.',
        'generic' => 'Something went wrong. Please try again later.',
    ],
];
