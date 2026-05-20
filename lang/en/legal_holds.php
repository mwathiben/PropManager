<?php

return [
    'unsupported_holdable_type' => 'This subject type cannot be placed under legal hold.',
    'subject_not_found' => 'Subject record not found.',
    'subject_not_owned' => 'You do not own this record.',
    'bulk_max_exceeded' => 'Bulk hold limit exceeded.',
    'date_range_exceeded' => 'Audit export window cannot exceed 2 years.',
    'reason_required' => 'A reason is required when placing a legal hold.',
    'delete_blocked' => 'This record is under an active legal hold and cannot be deleted. Release the hold first.',
    'delete_blocked_hint' => 'Under legal hold — release the hold before deleting.',

    'nav_legal_holds' => 'Legal holds',
    'page_title' => 'Legal holds',
    'tab_active' => 'Active',
    'tab_released' => 'Released',
    'create_modal_title' => 'Place under legal hold',
    'create_modal_warning' => 'Held subjects are excluded from retention purges until released.',
    'release_confirm' => 'Release this legal hold? Retention sweeps will resume on this subject.',
    'empty_state' => 'No legal holds yet.',
    'audit_export_title' => 'Audit export',

    'doc' => [
        'place' => 'Place hold',
        'release' => 'Release hold',
        'on_hold' => 'On hold',
    ],

    // Server-rendered mailable (Laravel :colon placeholders, not vue {curly}).
    'stale' => [
        'subject' => 'You have :count legal hold(s) that may need review',
        'heading' => 'Legal holds awaiting review',
        'greeting' => 'Hi :name,',
        'body' => 'The following :count legal hold(s) have been active for a long time. If the matter has resolved, please release them so retention sweeps can resume.',
        'col_subject' => 'Subject',
        'col_reason' => 'Reason',
        'col_days' => 'Days held',
        'cta' => 'Review legal holds',
        'footer' => 'No action is required if these holds are still needed. We will remind you again later.',
        'signoff' => '— :app',
    ],

    'history' => [
        'title' => 'Hold history',
        'held' => 'Placed on hold',
        'released' => 'Released',
        'active' => 'Active hold',
        'by' => 'by {name}',
        'reason' => 'Reason',
        'export_csv' => 'Export history CSV',
        'empty' => 'No hold history for this subject.',
        'view' => 'View hold history',
        'back' => 'Back to legal holds',
    ],
];
