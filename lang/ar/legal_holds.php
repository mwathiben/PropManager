<?php

return [
    'unsupported_holdable_type' => '[TODO-ar] This subject type cannot be placed under legal hold.',
    'subject_not_found' => '[TODO-ar] Subject record not found.',
    'subject_not_owned' => '[TODO-ar] You do not own this record.',
    'bulk_max_exceeded' => '[TODO-ar] Bulk hold limit exceeded.',
    'date_range_exceeded' => '[TODO-ar] Audit export window cannot exceed 2 years.',
    'reason_required' => '[TODO-ar] A reason is required when placing a legal hold.',
    'delete_blocked' => '[TODO-ar] This record is under an active legal hold and cannot be deleted. Release the hold first.',
    'delete_blocked_hint' => '[TODO-ar] Under legal hold — release the hold before deleting.',

    'nav_legal_holds' => '[TODO-ar] Legal holds',
    'page_title' => '[TODO-ar] Legal holds',
    'tab_active' => '[TODO-ar] Active',
    'tab_released' => '[TODO-ar] Released',
    'create_modal_title' => '[TODO-ar] Place under legal hold',
    'create_modal_warning' => '[TODO-ar] Held subjects are excluded from retention purges until released.',
    'release_confirm' => '[TODO-ar] Release this legal hold? Retention sweeps will resume on this subject.',
    'empty_state' => '[TODO-ar] No legal holds yet.',
    'audit_export_title' => '[TODO-ar] Audit export',

    'doc' => [
        'place' => '[TODO-ar] Place hold',
        'release' => '[TODO-ar] Release hold',
        'on_hold' => '[TODO-ar] On hold',
    ],

    'bulk' => [
        'selected' => '[TODO-ar] {count} document(s) selected',
        'place' => '[TODO-ar] Place hold on {count}',
        'release' => '[TODO-ar] Release {count} hold(s)',
        'placing' => '[TODO-ar] Placing…',
        'cap_hint' => '[TODO-ar] You can hold at most {max} at once.',
        'mixed_hint' => '[TODO-ar] Select all held or all unheld documents.',
        'select_all' => '[TODO-ar] Select all',
        'clear' => '[TODO-ar] Clear selection',
        'release_confirm' => '[TODO-ar] Release the holds on the selected documents?',
    ],

    'stale' => [
        'subject' => '[TODO-ar] You have :count legal hold(s) that may need review',
        'heading' => '[TODO-ar] Legal holds awaiting review',
        'greeting' => '[TODO-ar] Hi :name,',
        'body' => '[TODO-ar] The following :count legal hold(s) have been active for a long time. If the matter has resolved, please release them so retention sweeps can resume.',
        'col_subject' => '[TODO-ar] Subject',
        'col_reason' => '[TODO-ar] Reason',
        'col_days' => '[TODO-ar] Days held',
        'cta' => '[TODO-ar] Review legal holds',
        'footer' => '[TODO-ar] No action is required if these holds are still needed. We will remind you again later.',
        'signoff' => '— :app',
    ],

    'history' => [
        'title' => '[TODO-ar] Hold history',
        'held' => '[TODO-ar] Placed on hold',
        'released' => '[TODO-ar] Released',
        'active' => '[TODO-ar] Active hold',
        'by' => '[TODO-ar] by {name}',
        'reason' => '[TODO-ar] Reason',
        'export_csv' => '[TODO-ar] Export history CSV',
        'empty' => '[TODO-ar] No hold history for this subject.',
        'view' => '[TODO-ar] View hold history',
        'back' => '[TODO-ar] Back to legal holds',
    ],
];
