<?php

declare(strict_types=1);

return [
    'resume_banner' => [
        'title' => 'Step {current} of {total}',
        'subtitle' => "You're {pct}% through setup — pick up where you left off.",
        'continue' => 'Continue',
        'dismiss' => 'Dismiss',
    ],
    'tour' => [
        'aria_label' => 'Product tour',
        'step_of' => 'Step {current} of {total}',
        'nav' => [
            'back' => 'Back',
            'next' => 'Next',
            'skip' => 'Skip tour',
            'done' => 'Done',
        ],
        'landlord-dashboard' => [
            'welcome' => [
                'title' => 'Welcome to PropManager 👋',
                'body' => "Let's get your portfolio set up in a few quick steps. You can skip this tour anytime.",
            ],
            'add_building' => [
                'title' => 'Add your first building',
                'body' => 'Start here to register a building and its units — the home for everything else.',
            ],
            'add_unit' => [
                'title' => 'Add your units',
                'body' => 'Inside a building, add the units you rent out so you can place tenants in them.',
            ],
            'invite_tenant' => [
                'title' => 'Invite a tenant',
                'body' => 'Invite tenants by email or phone — each gets a portal to pay rent and raise issues.',
            ],
            'create_invoice' => [
                'title' => 'Bill your tenants',
                'body' => 'Raise rent invoices here, or let PropManager generate them automatically each cycle.',
            ],
            'record_payment' => [
                'title' => 'Record a payment',
                'body' => "When rent comes in, record it here and we'll keep the books and statements up to date.",
            ],
        ],
        'caretaker-intro' => [
            'welcome' => [
                'title' => 'Welcome 👋',
                'body' => "Here's a quick tour of your caretaker workspace.",
            ],
            'tickets' => [
                'title' => 'Your tickets',
                'body' => 'Maintenance requests assigned to you live here — accept, update, and resolve them.',
            ],
            'finish' => [
                'title' => "You're all set",
                'body' => 'Head to your dashboard any time for an overview of your work.',
            ],
        ],
        'tenant-intro' => [
            'welcome' => [
                'title' => 'Welcome home 👋',
                'body' => "Here's a quick tour of your tenant portal.",
            ],
            'finances' => [
                'title' => 'Your finances',
                'body' => 'See your rent invoices and payment history, and pay online right here.',
            ],
            'inbox' => [
                'title' => 'Messages',
                'body' => 'Chat directly with your landlord or caretaker — questions, requests, updates.',
            ],
        ],
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
