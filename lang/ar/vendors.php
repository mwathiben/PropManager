<?php

declare(strict_types=1);

return [
    'analytics' => [
        'forwarder_unavailable_heading' => '[TODO-ar] Analytics vendor unavailable',
        'vendor_disabled_helper' => '[TODO-ar] PostHog is disabled. Set VENDORS_POSTHOG_ENABLED=true and provide VENDORS_POSTHOG_API_KEY to enable.',
        'replay_batch_progress_label' => '[TODO-ar] Replaying analytics batch',
    ],
    'exp_stats' => [
        'chi_square_label' => '[TODO-ar] Chi-square (multi-variant)',
        'bayesian_posterior_label' => '[TODO-ar] Bayesian posterior P(B > A)',
        'sequential_alpha_spending_label' => '[TODO-ar] O\'Brien-Fleming alpha spending',
        'credible_interval_label' => '[TODO-ar] 95% credible interval',
    ],
    'push' => [
        'ios_install_first_heading' => '[TODO-ar] iOS Safari requires install first',
        'ios_install_helper' => '[TODO-ar] Tap Share → Add to Home Screen first, then enable push notifications.',
        'test_runner_heading' => '[TODO-ar] Push tester',
        'send_test_cta' => '[TODO-ar] Send push',
    ],
    'archive' => [
        'search_landlord_label' => '[TODO-ar] Landlord',
        'search_month_label' => '[TODO-ar] Month (YYYY-MM)',
        'rehydrate_cta' => '[TODO-ar] Rehydrate',
        'rows_loaded_label' => '[TODO-ar] Rows loaded',
    ],
    'observ' => [
        'vendor_flap_heading' => '[TODO-ar] Vendor flap detected',
        'click_through_rate_label' => '[TODO-ar] Push click-through rate (24h)',
    ],
];
