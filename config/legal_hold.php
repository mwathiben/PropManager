<?php

return [
    'bulk_max' => env('LEGAL_HOLD_BULK_MAX', 100),

    // Phase-68 STALE-SWEEP: a hold active longer than stale_after_days is
    // "stale" — the daily sweeper nudges the owning landlord to confirm or
    // release it, at most once per stale_reminder_cooldown_days.
    'stale_after_days' => (int) env('LEGAL_HOLD_STALE_AFTER_DAYS', 365),
    'stale_reminder_cooldown_days' => (int) env('LEGAL_HOLD_STALE_REMINDER_COOLDOWN_DAYS', 30),

    // Phase-72 WIZARD-FLOW: situation presets. The create-hold wizard offers
    // these as starting points — each pre-fills a reason template, pre-checks
    // the suggested subject types, and seeds review-by = today + review_days.
    // 'key' is stored on the matter as situation_type (validated against this
    // allow-list). reason_key / label_key resolve in lang/*/legal_holds.php.
    'situations' => [
        'litigation' => [
            'suggested_types' => ['Invoice', 'Ticket', 'Document', 'MessageThread'],
            'review_days' => 180,
        ],
        'regulator_request' => [
            'suggested_types' => ['Document', 'Invoice'],
            'review_days' => 90,
        ],
        'tenant_dispute' => [
            'suggested_types' => ['MessageThread', 'Ticket', 'Document'],
            'review_days' => 120,
        ],
        'data_subject_objection' => [
            'suggested_types' => ['Document', 'MessageThread'],
            'review_days' => 60,
        ],
    ],
];
