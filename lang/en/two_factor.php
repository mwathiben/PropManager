<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: two-factor authentication settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Two-Factor Authentication',
    'back_to_settings' => 'Back to Settings',
    'subtitle' => 'Add additional security to your account using two-factor authentication.',
    'enabled' => [
        'heading' => 'Two-factor authentication is enabled',
        'body' => 'Your account is protected with an authenticator app.',
    ],
    'recovery' => [
        'heading' => 'Recovery Codes',
        'remaining' => 'You have {count} recovery codes remaining.',
        'store_safely' => 'Store these codes safely - they can be used if you lose access to your authenticator app.',
        'view' => 'View Recovery Codes',
    ],
    'disable' => 'Disable Two-Factor',
    'required_notice' => 'Two-factor authentication is required for your account.',
    'disabled' => [
        'heading' => 'Two-factor authentication is not enabled',
        'body' => 'When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication.',
    ],
    'required_warning' => [
        'heading' => 'Action Required',
        'body' => 'Two-factor authentication is required for your account. Please enable it to continue using the application.',
    ],
    'enable' => 'Enable Two-Factor Authentication',
    'how' => [
        'heading' => 'How it works',
        'step1_title' => 'Install an authenticator app',
        'step1_body' => 'Download Google Authenticator, Authy, or Microsoft Authenticator on your phone.',
        'step2_title' => 'Scan the QR code',
        'step2_body' => 'Use your authenticator app to scan the QR code we provide.',
        'step3_title' => 'Enter the 6-digit code',
        'step3_body' => 'Enter the code from your app to verify and complete setup.',
    ],
    'password_modal' => [
        'title' => 'Confirm Password',
        'body' => 'Please confirm your password to continue.',
        'placeholder' => 'Password',
        'confirming' => 'Confirming...',
        'confirm' => 'Confirm',
    ],
    'disable_modal' => [
        'title' => 'Disable Two-Factor Authentication',
        'body' => 'Enter your password and a code from your authenticator app to disable two-factor authentication.',
        'password_label' => 'Password',
        'code_label' => 'Authentication Code',
        'code_placeholder' => 'Enter 6-digit code or recovery code',
        'disabling' => 'Disabling...',
        'disable' => 'Disable',
    ],
    'cancel' => 'Cancel',
];
