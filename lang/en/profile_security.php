<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: profile security (password-change) tab. Mirror en/sw/ar.
 */
return [
    'banner' => [
        'title' => 'Account Security',
        'body' => 'Keep your account secure by using a strong password and enabling two-factor authentication.',
    ],
    'update' => [
        'heading' => 'Update Password',
        'subheading' => 'Ensure your account is using a strong, unique password',
    ],
    'fields' => [
        'current_password' => 'Current Password',
        'current_password_placeholder' => 'Enter your current password',
        'new_password' => 'New Password',
        'new_password_placeholder' => 'Enter a new password',
        'confirm_password' => 'Confirm New Password',
        'confirm_password_placeholder' => 'Confirm your new password',
    ],
    'updated' => 'Password updated.',
    'updating' => 'Updating...',
    'update_button' => 'Update Password',
    'requirements' => [
        'title' => 'Password Requirements',
        'length' => 'At least 8 characters long',
        'case' => 'Include uppercase and lowercase letters',
        'number' => 'Include at least one number',
        'special' => 'Include at least one special character',
    ],
];
