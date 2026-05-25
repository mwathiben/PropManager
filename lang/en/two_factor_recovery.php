<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: two-factor recovery codes page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Recovery Codes',
    'back_to_2fa' => 'Back to Two-Factor Authentication',
    'heading' => 'Recovery Codes',
    'intro' => 'Use these codes to access your account if you lose your authenticator device.',
    'warning' => [
        'prefix' => 'Important:',
        'once' => 'once',
        'used_part1' => 'Each code can only be used',
        'used_part2' => '.',
        'safe_place' => 'Store these codes in a safe place (password manager, secure document, etc.).',
        'anyone' => 'Anyone with these codes can access your account.',
    ],
    'actions' => [
        'copy' => 'Copy',
        'download' => 'Download',
        'print' => 'Print',
        'regenerate' => 'Regenerate Codes',
    ],
    'back_to_settings' => '← Back to Two-Factor Settings',
    'regenerate' => [
        'title' => 'Regenerate Recovery Codes',
        'description' => 'This will invalidate your existing recovery codes and generate new ones. Make sure to save the new codes.',
        'confirm_password' => 'Confirm Password',
        'cancel' => 'Cancel',
        'submit' => 'Regenerate',
        'submitting' => 'Regenerating...',
    ],
    'download' => [
        'file_header' => 'PropManager Recovery Codes',
        'generated' => 'Generated: {date}',
        'important' => 'IMPORTANT: Store these codes safely. Each code can only be used once.',
        'footer' => 'If you lose access to your authenticator app, you can use one of these codes to log in.',
    ],
];
