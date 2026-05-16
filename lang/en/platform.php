<?php

declare(strict_types=1);

return [
    'experiments' => [
        'dashboard_heading' => 'Experiments',
        'status_draft' => 'Draft',
        'status_running' => 'Running',
        'status_paused' => 'Paused',
        'status_concluded' => 'Concluded',
    ],
    'metered' => [
        'usage_heading' => 'Metered usage',
        'limit_label' => 'Plan limit',
        'ratio_label' => 'Usage ratio',
        'overage_warning' => 'You have exceeded the plan limit for this feature.',
    ],
    'analytics' => [
        'event_browser_heading' => 'Event browser',
        'event_count_label' => '24h count',
        'top_events_label' => 'Top events',
    ],
    'billing' => [
        'proration_label' => 'Prorated amount',
        'scheduled_downgrade_label' => 'Scheduled for downgrade at period end',
        'change_history_heading' => 'Plan change history',
    ],
    'notifications' => [
        'preferences_heading' => 'Notification preferences',
        'lifecycle_label' => 'Marketing + lifecycle emails',
        'transactional_locked_label' => 'Cannot disable — required for billing transparency.',
    ],
];
