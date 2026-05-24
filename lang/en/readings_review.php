<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: water-meter reading review page. Mirror en/sw/ar.
 */
return [
    'title' => 'Review Water Readings',
    'pending_count' => '{count} pending reading(s) awaiting approval',
    'filters' => [
        'building' => 'Building',
        'all_buildings' => 'All Buildings',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'apply' => 'Apply',
        'reset' => 'Reset',
    ],
    'empty' => [
        'title' => 'No pending readings to review',
        'body' => 'All readings have been approved or rejected',
    ],
    'card' => [
        'meter_photo' => 'Meter Photo',
        'meter_photo_alt' => 'Meter Reading Photo',
        'no_photo' => 'No photo available',
        'reading_details' => 'Reading Details',
        'unit' => 'Unit:',
        'building' => 'Building:',
        'reading_date' => 'Reading Date:',
        'recorded_by' => 'Recorded By:',
        'consumption_cost' => 'Consumption & Cost',
        'previous_reading' => 'Previous Reading:',
        'manual_reading' => 'Manual Reading:',
        'ocr_reading' => 'OCR Reading:',
        'verified' => 'Verified',
        'diff' => 'Diff: {value}',
        'consumption' => 'Consumption:',
        'consumption_value' => '{units} units',
        'cost' => 'Cost:',
    ],
    'actions' => [
        'approve' => 'Approve',
        'reject' => 'Reject',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} readings',
    ],
    'modal' => [
        'unit' => 'Unit:',
        'reading' => 'Reading:',
        'cost' => 'Cost:',
        'cancel' => 'Cancel',
    ],
    'approve' => [
        'title' => 'Approve Water Reading',
        'notes_label' => 'Notes (Optional)',
        'notes_placeholder' => 'Add any notes about this approval...',
        'processing' => 'Approving...',
        'confirm' => 'Confirm Approval',
    ],
    'reject' => [
        'title' => 'Reject Water Reading',
        'reason_label' => 'Reason for Rejection',
        'reason_placeholder' => 'Explain why this reading is being rejected...',
        'reason_required' => 'Please provide a reason for rejection',
        'processing' => 'Rejecting...',
        'confirm' => 'Confirm Rejection',
    ],
];
