<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: user-profile document-verification tab. Mirror en/sw/ar.
 */
return [
    'status' => [
        'verified' => 'Verified',
        'incomplete' => 'Verification Incomplete',
        'verified_body' => 'Your profile has been verified',
        'incomplete_body' => 'Complete all fields to verify your profile',
    ],
    'fields' => [
        'phone' => 'Phone Number',
        'national_id' => 'National ID',
        'emergency_contact' => 'Emergency Contact',
        'profile_photo' => 'Profile Photo',
    ],
    'identity' => [
        'heading' => 'Identity Information',
        'subtitle' => 'Your contact and identification details',
        'phone_label' => 'Phone Number',
        'phone_placeholder' => '+254 712 345 678',
        'national_id_label' => 'National ID / Passport',
        'national_id_placeholder' => 'Enter your ID number',
    ],
    'emergency' => [
        'heading' => 'Emergency Contact',
        'subtitle' => 'Someone we can contact in case of an emergency',
        'name_label' => 'Contact Name',
        'name_placeholder' => 'Full name',
        'phone_label' => 'Contact Phone',
        'phone_placeholder' => '+254 712 345 678',
    ],
    'info' => [
        'heading' => 'Why we need this information',
        'body' => 'Your verification information helps us maintain accurate records and contact you or your emergency contact if needed. This information is encrypted and kept secure.',
    ],
    'saved' => 'Verification info saved.',
    'saving' => 'Saving...',
    'submit' => 'Save Verification Info',
];
