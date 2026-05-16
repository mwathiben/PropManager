<?php

declare(strict_types=1);

return [
    'push' => [
        'permission_default_helper' => 'Click Enable push to receive browser notifications.',
        'permission_denied_helper' => 'Push is blocked at the browser level — enable it from site settings to subscribe.',
        'permission_granted_helper' => 'Push notifications are enabled for this browser.',
        'subscribe_cta' => 'Enable push',
        'unsubscribe_cta' => 'Disable push',
        'manage_via_browser_helper' => 'Manage in browser settings',
        'push_disabled_until_permission_helper' => 'Enable browser notifications first to toggle the push channel.',
    ],
    'digest' => [
        'mail_subject' => 'Your weekly PropManager insight, :landlord',
        'weekly_summary_heading' => 'Your weekly insight digest',
        'greeting' => 'Hi :name,',
        'engagement_score_label' => 'Engagement score',
        'delta_7d_suffix' => 'vs last week',
        'usage_ratios_heading' => 'Plan usage this period',
        'feature_column' => 'Feature',
        'usage_column' => 'Used',
        'limit_column' => 'Limit',
        'referrals_label' => 'Referrals attributed (30d)',
        'current_plan_label' => 'Current plan',
        'cta_open_dashboard' => 'Open dashboard',
        'signature' => "— The :app team",
        'opt_out_link_label' => 'Manage notification preferences',
        'opt_out_link_helper' => 'turn off the weekly digest without losing payment notifications',
    ],
    'gateway' => [
        'proration_failed_heading' => 'Plan change synced locally — gateway sync pending',
        'dunning_inline_helper' => 'Paystack reported a failed charge; the dunning sequence has started.',
        'sync_pending_label' => 'Gateway sync pending',
    ],
    'admin' => [
        'experiments_index_heading' => 'Experiments',
        'create_experiment_cta' => 'New experiment',
        'conclude_experiment_cta' => 'Conclude experiment',
        'significance_table_caption' => 'Two-proportion z-test (alpha = 0.05)',
        'variant_weight_label' => 'Weight',
        'variant_users_assigned_label' => 'Users assigned',
        'status_draft_label' => 'Draft',
        'status_running_label' => 'Running',
        'status_paused_label' => 'Paused',
        'status_concluded_label' => 'Concluded',
    ],
    'retention' => [
        'cold_storage_rollover_heading' => 'Cold storage rollover',
        'prune_window_label' => 'Retention window (days)',
        'archive_disk_label' => 'Archive disk',
    ],
];
