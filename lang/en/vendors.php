<?php

declare(strict_types=1);

return [
    'analytics' => [
        'forwarder_unavailable_heading' => 'Analytics vendor unavailable',
        'vendor_disabled_helper' => 'PostHog is disabled. Set VENDORS_POSTHOG_ENABLED=true and provide VENDORS_POSTHOG_API_KEY to enable.',
        'replay_batch_progress_label' => 'Replaying analytics batch',
    ],
    'exp_stats' => [
        'chi_square_label' => 'Chi-square (multi-variant)',
        'bayesian_posterior_label' => 'Bayesian posterior P(B > A)',
        'sequential_alpha_spending_label' => "O'Brien-Fleming alpha spending",
        'credible_interval_label' => '95% credible interval',
    ],
    'push' => [
        'ios_install_first_heading' => 'iOS Safari requires install first',
        'ios_install_helper' => 'Tap Share → Add to Home Screen first, then enable push notifications.',
        'test_runner_heading' => 'Push tester',
        'send_test_cta' => 'Send push',
    ],
    'archive' => [
        'search_landlord_label' => 'Landlord',
        'search_month_label' => 'Month (YYYY-MM)',
        'rehydrate_cta' => 'Rehydrate',
        'rows_loaded_label' => 'Rows loaded',
    ],
    'observ' => [
        'vendor_flap_heading' => 'Vendor flap detected',
        'click_through_rate_label' => 'Push click-through rate (24h)',
    ],

    'performance' => [
        'title' => 'Vendor performance',
        'subtitle' => 'Compare your vendors on SLA, resolution time, and cost',
        'window' => 'Window',
        'days' => 'Last {count} days',
        'empty' => 'No active vendors yet.',
        'col_vendor' => 'Vendor',
        'col_within_sla' => 'Within SLA',
        'col_avg_resolution' => 'Avg resolution',
        'col_resolved' => 'Resolved',
        'col_overdue' => 'Open overdue',
        'col_cost_per_ticket' => 'Cost / ticket',
    ],
];
