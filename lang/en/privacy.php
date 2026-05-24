<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: privacy & data settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Privacy Settings',
    'back_to_settings' => 'Back to Settings',
    'heading' => 'Privacy & Data',
    'subheading' => 'Manage your personal data and exercise your privacy rights.',
    'export' => [
        'heading' => 'Export Your Data',
        'description_line1' => 'Download a copy of all your personal data stored in PropManager.',
        'description_line2' => 'This includes your profile information, lease history, invoices, payments, and uploaded documents.',
        'legal_note' => 'Under GDPR Article 20 and Kenya DPA Section 26, you have the right to receive your data in a portable format.',
        'request_button' => 'Request Data Export',
        'download_now' => 'Download Now',
        'modal_heading' => 'Export Your Data',
        'modal_body_line1' => "We'll prepare a ZIP file containing all your personal data. This may take a few minutes",
        'modal_body_line2' => "for larger accounts. You'll receive an email when your export is ready.",
        'requesting' => 'Requesting...',
        'request_export' => 'Request Export',
    ],
    'delete' => [
        'heading' => 'Delete Your Account',
        'scheduled_title' => 'Deletion Scheduled',
        'scheduled_prefix' => 'Your account is scheduled for deletion on',
        'scheduled_suffix' => '.',
        'days_remaining_prefix' => 'You have',
        'days_remaining_value' => '{days} days',
        'days_remaining_suffix' => 'to cancel this request.',
        'cancel_request' => 'Cancel Deletion Request →',
        'blockers_intro' => 'Account deletion is not available due to the following:',
        'normal_description_line1' => 'Permanently delete your account and all associated data.',
        'normal_description_line2' => 'This action cannot be undone after the {days}-day grace period.',
        'legal_note' => 'Under GDPR Article 17 and Kenya DPA Section 28, you have the right to erasure ("right to be forgotten").',
        'request_button' => 'Request Account Deletion',
        'modal_heading' => 'Delete Your Account',
        'warning_label' => 'Warning:',
        'warning_body' => 'This will permanently delete your account and all associated data after a {days}-day grace period. This action cannot be undone.',
        'reason_label' => 'Reason for leaving (optional)',
        'reason_placeholder' => 'Help us improve by sharing your reason...',
        'cancel' => 'Cancel',
        'processing' => 'Processing...',
        'confirm_button' => 'Delete My Account',
    ],
    'rights' => [
        'heading' => 'Your Data Rights',
        'access_label' => 'Access:',
        'access_body' => 'Request a copy of your personal data',
        'portability_label' => 'Portability:',
        'portability_body' => 'Receive your data in a machine-readable format',
        'erasure_label' => 'Erasure:',
        'erasure_body' => 'Request deletion of your personal data',
        'rectification_label' => 'Rectification:',
        'rectification_body' => 'Correct inaccurate data via your profile settings',
        'object_label' => 'Object:',
        'object_body' => 'Opt out of marketing communications',
    ],
];
