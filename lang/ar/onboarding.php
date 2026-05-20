<?php

declare(strict_types=1);

return [
    'resume_banner' => [
        'title' => '[TODO-ar] Step {current} of {total}',
        'subtitle' => '[TODO-ar] You\'re {pct}% through setup — pick up where you left off.',
        'continue' => '[TODO-ar] Continue',
        'dismiss' => '[TODO-ar] Dismiss',
    ],
    'tour' => [
        'aria_label' => '[TODO-ar] Product tour',
        'step_of' => '[TODO-ar] Step {current} of {total}',
        'nav' => [
            'back' => '[TODO-ar] Back',
            'next' => '[TODO-ar] Next',
            'skip' => '[TODO-ar] Skip tour',
            'done' => '[TODO-ar] Done',
        ],
        'landlord-dashboard' => [
            'welcome' => [
                'title' => '[TODO-ar] Welcome to PropManager 👋',
                'body' => "[TODO-ar] Let's get your portfolio set up in a few quick steps. You can skip this tour anytime.",
            ],
            'add_building' => [
                'title' => '[TODO-ar] Add your first building',
                'body' => '[TODO-ar] Start here to register a building and its units — the home for everything else.',
            ],
            'add_unit' => [
                'title' => '[TODO-ar] Add your units',
                'body' => '[TODO-ar] Inside a building, add the units you rent out so you can place tenants in them.',
            ],
            'invite_tenant' => [
                'title' => '[TODO-ar] Invite a tenant',
                'body' => '[TODO-ar] Invite tenants by email or phone — each gets a portal to pay rent and raise issues.',
            ],
            'create_invoice' => [
                'title' => '[TODO-ar] Bill your tenants',
                'body' => '[TODO-ar] Raise rent invoices here, or let PropManager generate them automatically each cycle.',
            ],
            'record_payment' => [
                'title' => '[TODO-ar] Record a payment',
                'body' => "[TODO-ar] When rent comes in, record it here and we'll keep the books and statements up to date.",
            ],
        ],
        'caretaker-intro' => [
            'welcome' => [
                'title' => '[TODO-ar] Welcome 👋',
                'body' => "[TODO-ar] Here's a quick tour of your caretaker workspace.",
            ],
            'tickets' => [
                'title' => '[TODO-ar] Your tickets',
                'body' => '[TODO-ar] Maintenance requests assigned to you live here — accept, update, and resolve them.',
            ],
            'finish' => [
                'title' => "[TODO-ar] You're all set",
                'body' => '[TODO-ar] Head to your dashboard any time for an overview of your work.',
            ],
        ],
        'tenant-intro' => [
            'welcome' => [
                'title' => '[TODO-ar] Welcome home 👋',
                'body' => "[TODO-ar] Here's a quick tour of your tenant portal.",
            ],
            'finances' => [
                'title' => '[TODO-ar] Your finances',
                'body' => '[TODO-ar] See your rent invoices and payment history, and pay online right here.',
            ],
            'inbox' => [
                'title' => '[TODO-ar] Messages',
                'body' => '[TODO-ar] Chat directly with your landlord or caretaker — questions, requests, updates.',
            ],
        ],
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
