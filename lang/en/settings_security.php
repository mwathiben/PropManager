<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: account-settings security tab (Settings/partials/SecurityTab).
 * Page-specific namespace, distinct from profile_security. Mirror en/sw/ar.
 */
return [
    'heading' => 'Security & Privacy',
    'subheading' => 'Manage your account security and data privacy settings.',
    'links' => [
        'two_factor' => [
            'title' => 'Two-Factor Authentication',
            'description' => 'Add an extra layer of security to your account with 2FA',
        ],
        'password' => [
            'title' => 'Password & Profile',
            'description' => 'Update your password and personal information',
        ],
        'privacy' => [
            'title' => 'Privacy & Data',
            'description' => 'Export or delete your personal data (GDPR compliance)',
        ],
    ],
    'status' => [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
    ],
    'recommendations' => [
        'title' => 'Security Recommendations',
        'enable_2fa' => 'Enable two-factor authentication',
        'done' => 'Done',
        'strong_password' => 'Use a strong, unique password',
        'review_privacy' => 'Review your data privacy settings regularly',
    ],
    'account_status' => [
        'title' => 'Account Security Status',
        'two_factor' => 'Two-Factor Authentication',
        'two_factor_protected' => 'Your account is protected with 2FA',
        'two_factor_not_enabled' => 'Not enabled - we recommend enabling 2FA',
        'data_privacy' => 'Data Privacy',
        'data_privacy_desc' => 'GDPR compliant data handling',
    ],
];
