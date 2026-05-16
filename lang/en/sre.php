<?php

declare(strict_types=1);

return [
    'incident' => [
        'opened' => 'Incident opened.',
        'status_updated' => 'Incident status updated.',
        'status_open' => 'Open',
        'status_investigating' => 'Investigating',
        'status_mitigated' => 'Mitigated',
        'status_resolved' => 'Resolved',
        'severity_sev1' => 'SEV1 — critical, page now',
        'severity_sev2' => 'SEV2 — major, page on-call',
        'severity_sev3' => 'SEV3 — minor, email on-call',
        'severity_sev4' => 'SEV4 — informational',
    ],
    'alert' => [
        'acknowledge_button' => 'Acknowledge',
        'acknowledged_success' => 'Alert acknowledged.',
    ],
    'post_mortem' => [
        'heading' => 'Blameless Post-Mortem',
        'sections' => [
            'summary' => 'Summary',
            'timeline' => 'Timeline',
            'root_cause' => 'Root cause',
            'contributing_factors' => 'Contributing factors',
            'customer_impact' => 'Customer impact',
            'action_items' => 'Action items',
            'lessons_learned' => 'Lessons learned',
        ],
    ],
    'dependency' => [
        'status_up' => 'Up',
        'status_degraded' => 'Degraded',
        'status_down' => 'Down',
    ],
];
