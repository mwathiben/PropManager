<?php

declare(strict_types=1);

return [
    'resume_banner' => [
        'title' => 'Step :current of :total',
        'subtitle' => "You're :pct% through setup — pick up where you left off.",
        'continue' => 'Continue',
        'dismiss' => 'Dismiss',
    ],
    'wizard' => [
        'skip_button' => 'Skip for now',
        'resume_cta' => 'Resume setup',
    ],
    'sample' => [
        'populated_success' => 'Sample data loaded. You can reset it any time.',
        'reset_success' => ':count sample run(s) cleared.',
        'refused_real_data' => 'Cannot load sample data while you already have an active lease.',
        'populate_button' => 'Load sample data',
        'reset_button' => 'Reset sample data',
    ],
    'help' => [
        'drawer_title' => 'Help',
        'search_placeholder' => 'Search help articles',
        'no_results' => 'No articles match your search.',
    ],
    'checklist' => [
        'heading' => 'Finish setting up',
        'dismiss' => 'Dismiss',
        'steps' => [
            'first_property' => 'Add your first property',
            'first_unit' => 'Add your first unit',
            'first_tenant' => 'Invite your first tenant',
            'first_invoice' => 'Generate your first invoice',
            'first_payment' => 'Record your first payment',
        ],
    ],
    'video' => [
        'label' => 'Walkthrough video',
    ],
    'nudge' => [
        'subject' => 'Pick up where you left off',
        'heading' => 'Welcome back to PropManager',
        'greeting' => 'Hi :name,',
        'body' => "You're partway through setup — your next step is **:step**. Pick up where you left off using the link below.",
        'cta' => 'Resume setup',
        'expiry_note' => 'This link is valid for 7 days. If it expires, the next nudge email will include a fresh one.',
        'signoff' => 'Thanks — the :app team',
    ],
];
