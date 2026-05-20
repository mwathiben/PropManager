<?php

declare(strict_types=1);

return [
    // Global kill-switch. Set NPS_ENABLED=false to instantly stop every
    // prompt (incident response, or if NPS data is found harmful).
    'enabled' => env('NPS_ENABLED', true),

    // One response per user per this many days.
    'cadence_days' => env('NPS_CADENCE_DAYS', 90),

    // "Not now" snoozes the prompt for this many days.
    'snooze_days' => env('NPS_SNOOZE_DAYS', 30),

    // After being shown, suppress re-prompting for this many days so
    // page-to-page navigation doesn't re-nag within a session.
    'reprompt_cooldown_days' => env('NPS_REPROMPT_COOLDOWN_DAYS', 1),

    // Stop asking entirely after this many dismissals.
    'max_dismissals' => env('NPS_MAX_DISMISSALS', 3),

    // Accounts younger than this are too new to survey meaningfully.
    'min_account_age_days' => env('NPS_MIN_ACCOUNT_AGE_DAYS', 14),

    // Allow-list of valid prompt contexts (validated on store).
    'contexts' => ['dashboard', 'post_payment', 'post_resolution'],
];
