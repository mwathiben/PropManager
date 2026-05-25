<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant verification conduct page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Verify {name}',
    'heading' => 'Tenant Verification',
    'verify_subtitle' => 'Verify {name}',
    'manage_templates' => 'Manage Templates',
    'unit_line' => 'Unit {number} - {building}',
    'start' => [
        'title' => 'Start Verification',
        'description' => 'Select a verification template to begin the verification process for this tenant.',
        'select_template' => 'Select Template',
        'choose_template' => 'Choose a template...',
        'option' => '{name} ({count} items)',
        'option_default_suffix' => ' - Default',
        'button' => 'Start Verification',
        'starting' => 'Starting...',
        'no_templates' => 'No verification templates found.',
        'create_one' => 'Create one first',
    ],
    'progress' => [
        'title' => 'Verification Progress',
        'percent_complete' => '{percent}% Complete',
    ],
    'stats' => [
        'verified' => 'Verified',
        'waived' => 'Waived',
        'pending' => 'Pending',
        'rejected' => 'Rejected',
    ],
    'checklist' => [
        'title' => 'Verification Checklist',
        'reset' => 'Reset',
    ],
    'item' => [
        'required' => 'Required',
        'note_prefix' => 'Note: {note}',
        'audit' => '{action} by {name} on {date}',
    ],
    'action_label' => [
        'verified' => 'Verified',
        'waived' => 'Waived',
        'rejected' => 'Rejected',
        'updated' => 'Updated',
    ],
    'title' => [
        'add_note' => 'Add note',
        'verify' => 'Verify',
        'reject' => 'Reject',
        'waive' => 'Waive',
        'reset_pending' => 'Reset to pending',
    ],
    'complete' => [
        'pending_notice' => '{count} required item(s) pending',
        'rejected_notice' => ', {count} rejected',
        'ready' => 'All required items verified. Ready to complete!',
        'button' => 'Complete Verification',
    ],
    'note_modal' => [
        'title' => 'Add Note',
        'placeholder' => 'Add a note about this verification item...',
        'cancel' => 'Cancel',
        'save' => 'Save Note',
    ],
    'alert' => [
        'select_template' => 'Please select a template',
        'required_first' => 'All required items must be verified or waived before completing.',
    ],
    'confirm' => [
        'reset' => 'Are you sure you want to reset the verification? All progress will be lost.',
        'complete' => 'Complete verification for this tenant? This will mark the lease as verified.',
    ],
];
