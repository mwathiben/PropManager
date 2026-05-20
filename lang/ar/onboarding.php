<?php

declare(strict_types=1);

return [
    'resume_banner' => [
        'title' => '[TODO-ar] Step {current} of {total}',
        'subtitle' => '[TODO-ar] You\'re {pct}% through setup — pick up where you left off.',
        'continue' => '[TODO-ar] Continue',
        'dismiss' => '[TODO-ar] Dismiss',
    ],
    'wizard' => [
        'skip_button' => '[TODO-ar] Skip for now',
        'resume_cta' => '[TODO-ar] Resume setup',
    ],
    'sample' => [
        'populated_success' => '[TODO-ar] Sample data loaded. You can reset it any time.',
        'reset_success' => '[TODO-ar] :count sample run(s) cleared.',
        'refused_real_data' => '[TODO-ar] Cannot load sample data while you already have an active lease.',
        'populate_button' => '[TODO-ar] Load sample data',
        'reset_button' => '[TODO-ar] Reset sample data',
    ],
    'help' => [
        'drawer_title' => '[TODO-ar] Help',
        'search_placeholder' => '[TODO-ar] Search help articles',
        'no_results' => '[TODO-ar] No articles match your search.',
    ],
    'checklist' => [
        'heading' => '[TODO-ar] Finish setting up',
        'dismiss' => '[TODO-ar] Dismiss',
        'steps' => [
            'first_property' => '[TODO-ar] Add your first property',
            'first_unit' => '[TODO-ar] Add your first unit',
            'first_tenant' => '[TODO-ar] Invite your first tenant',
            'first_invoice' => '[TODO-ar] Generate your first invoice',
            'first_payment' => '[TODO-ar] Record your first payment',
        ],
    ],
    'video' => [
        'label' => '[TODO-ar] Walkthrough video',
    ],
    'nudge' => [
        'subject' => '[TODO-ar] Pick up where you left off',
        'heading' => '[TODO-ar] Welcome back to PropManager',
        'greeting' => '[TODO-ar] Hi :name,',
        'body' => "[TODO-ar] You're partway through setup — your next step is **:step**.",
        'cta' => '[TODO-ar] Resume setup',
        'expiry_note' => '[TODO-ar] This link is valid for 7 days.',
        'signoff' => '[TODO-ar] Thanks — the :app team',
    ],
];
