<?php

declare(strict_types=1);

return [
    'push' => [
        'permission_default_helper' => '[TODO-ar] Click Enable push to receive browser notifications.',
        'permission_denied_helper' => '[TODO-ar] Push is blocked at the browser level — enable it from site settings to subscribe.',
        'permission_granted_helper' => '[TODO-ar] Push notifications are enabled for this browser.',
        'subscribe_cta' => '[TODO-ar] Enable push',
        'unsubscribe_cta' => '[TODO-ar] Disable push',
        'manage_via_browser_helper' => '[TODO-ar] Manage in browser settings',
        'push_disabled_until_permission_helper' => '[TODO-ar] Enable browser notifications first to toggle the push channel.',
    ],
    'digest' => [
        'mail_subject' => '[TODO-ar] Your weekly PropManager insight, :landlord',
        'weekly_summary_heading' => '[TODO-ar] Your weekly insight digest',
        'greeting' => '[TODO-ar] Hi :name,',
        'engagement_score_label' => '[TODO-ar] Engagement score',
        'delta_7d_suffix' => '[TODO-ar] vs last week',
        'usage_ratios_heading' => '[TODO-ar] Plan usage this period',
        'feature_column' => '[TODO-ar] Feature',
        'usage_column' => '[TODO-ar] Used',
        'limit_column' => '[TODO-ar] Limit',
        'referrals_label' => '[TODO-ar] Referrals attributed (30d)',
        'current_plan_label' => '[TODO-ar] Current plan',
        'cta_open_dashboard' => '[TODO-ar] Open dashboard',
        'signature' => '[TODO-ar] — The :app team',
        'opt_out_link_label' => '[TODO-ar] Manage notification preferences',
        'opt_out_link_helper' => '[TODO-ar] turn off the weekly digest without losing payment notifications',
    ],
    'gateway' => [
        'proration_failed_heading' => '[TODO-ar] Plan change synced locally — gateway sync pending',
        'dunning_inline_helper' => '[TODO-ar] Paystack reported a failed charge; the dunning sequence has started.',
        'sync_pending_label' => '[TODO-ar] Gateway sync pending',
    ],
    'admin' => [
        'experiments_index_heading' => '[TODO-ar] Experiments',
        'create_experiment_cta' => '[TODO-ar] New experiment',
        'conclude_experiment_cta' => '[TODO-ar] Conclude experiment',
        'significance_table_caption' => '[TODO-ar] Two-proportion z-test (alpha = 0.05)',
        'variant_weight_label' => '[TODO-ar] Weight',
        'variant_users_assigned_label' => '[TODO-ar] Users assigned',
        'status_draft_label' => '[TODO-ar] Draft',
        'status_running_label' => '[TODO-ar] Running',
        'status_paused_label' => '[TODO-ar] Paused',
        'status_concluded_label' => '[TODO-ar] Concluded',
    ],
    'retention' => [
        'cold_storage_rollover_heading' => '[TODO-ar] Cold storage rollover',
        'prune_window_label' => '[TODO-ar] Retention window (days)',
        'archive_disk_label' => '[TODO-ar] Archive disk',
    ],
];
