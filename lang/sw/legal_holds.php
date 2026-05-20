<?php

return [
    'unsupported_holdable_type' => '[TODO-sw] This subject type cannot be placed under legal hold.',
    'subject_not_found' => '[TODO-sw] Subject record not found.',
    'subject_not_owned' => '[TODO-sw] You do not own this record.',
    'bulk_max_exceeded' => '[TODO-sw] Bulk hold limit exceeded.',
    'date_range_exceeded' => '[TODO-sw] Audit export window cannot exceed 2 years.',
    'reason_required' => '[TODO-sw] A reason is required when placing a legal hold.',
    'delete_blocked' => '[TODO-sw] This record is under an active legal hold and cannot be deleted. Release the hold first.',
    'delete_blocked_hint' => '[TODO-sw] Under legal hold — release the hold before deleting.',

    'nav_legal_holds' => '[TODO-sw] Legal holds',
    'page_title' => '[TODO-sw] Legal holds',
    'tab_active' => '[TODO-sw] Active',
    'tab_released' => '[TODO-sw] Released',
    'create_modal_title' => '[TODO-sw] Place under legal hold',
    'create_modal_warning' => '[TODO-sw] Held subjects are excluded from retention purges until released.',
    'release_confirm' => '[TODO-sw] Release this legal hold? Retention sweeps will resume on this subject.',
    'empty_state' => '[TODO-sw] No legal holds yet.',
    'audit_export_title' => '[TODO-sw] Audit export',

    'doc' => [
        'place' => '[TODO-sw] Place hold',
        'release' => '[TODO-sw] Release hold',
        'on_hold' => '[TODO-sw] On hold',
    ],

    'stale' => [
        'subject' => '[TODO-sw] You have :count legal hold(s) that may need review',
        'heading' => '[TODO-sw] Legal holds awaiting review',
        'greeting' => '[TODO-sw] Hi :name,',
        'body' => '[TODO-sw] The following :count legal hold(s) have been active for a long time. If the matter has resolved, please release them so retention sweeps can resume.',
        'col_subject' => '[TODO-sw] Subject',
        'col_reason' => '[TODO-sw] Reason',
        'col_days' => '[TODO-sw] Days held',
        'cta' => '[TODO-sw] Review legal holds',
        'footer' => '[TODO-sw] No action is required if these holds are still needed. We will remind you again later.',
        'signoff' => '— :app',
    ],

    'history' => [
        'title' => '[TODO-sw] Hold history',
        'held' => '[TODO-sw] Placed on hold',
        'released' => '[TODO-sw] Released',
        'active' => '[TODO-sw] Active hold',
        'by' => '[TODO-sw] by {name}',
        'reason' => '[TODO-sw] Reason',
        'export_csv' => '[TODO-sw] Export history CSV',
        'empty' => '[TODO-sw] No hold history for this subject.',
        'view' => '[TODO-sw] View hold history',
        'back' => '[TODO-sw] Back to legal holds',
    ],
];
