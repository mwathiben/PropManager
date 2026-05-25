<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: water meter readings entry page. Mirror en/sw/ar.
 */
return [
    'title' => 'Water Readings',
    'header' => [
        'title_caretaker' => 'Water Meter Input',
        'title_landlord' => 'Record Water Readings',
        'subtitle_caretaker' => 'Submit readings for landlord approval',
        'subtitle_landlord' => 'Enter meter readings for billing',
    ],
    'review_pending' => 'Review Pending →',
    'previous' => 'Previous: {value}',
    'last_reading' => 'Last reading: {value}',
    'new_reading_placeholder' => 'New Reading',
    'upload_meter_photo' => 'Upload Meter Photo',
    'meter_photo_alt' => 'Meter photo preview',
    'photo_uploaded' => 'Photo uploaded',
    'photo_required' => [
        'label' => 'Photo Required:',
        'caretaker' => 'Each reading must include a photo of the water meter for landlord verification. Readings will be submitted for approval before being added to invoices.',
        'landlord' => 'Each reading must include a photo of the water meter. Photos help verify accuracy and prevent billing disputes.',
    ],
    'submit' => [
        'processing' => 'Submitting...',
        'caretaker' => 'Submit Readings for Approval',
        'landlord' => 'Save Readings',
    ],
    'alert' => [
        'invalid_image' => 'Please select a valid image file',
        'photo_too_large' => 'Photo size must be less than 5MB',
        'no_readings' => 'Please enter at least one reading with a photo.',
        'incomplete' => 'Some readings are incomplete. Please provide both meter reading and photo for each entry.',
        'submitted' => 'Readings submitted for landlord approval!',
    ],
];
