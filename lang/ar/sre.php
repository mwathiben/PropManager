<?php

declare(strict_types=1);

return [
    'incident' => [
        'opened' => '[TODO-ar] Incident opened.',
        'status_updated' => '[TODO-ar] Incident status updated.',
        'status_open' => '[TODO-ar] Open',
        'status_investigating' => '[TODO-ar] Investigating',
        'status_mitigated' => '[TODO-ar] Mitigated',
        'status_resolved' => '[TODO-ar] Resolved',
        'severity_sev1' => '[TODO-ar] SEV1 — critical, page now',
        'severity_sev2' => '[TODO-ar] SEV2 — major, page on-call',
        'severity_sev3' => '[TODO-ar] SEV3 — minor, email on-call',
        'severity_sev4' => '[TODO-ar] SEV4 — informational',
    ],
    'alert' => [
        'acknowledge_button' => '[TODO-ar] Acknowledge',
        'acknowledged_success' => '[TODO-ar] Alert acknowledged.',
    ],
    'post_mortem' => [
        'heading' => '[TODO-ar] Blameless Post-Mortem',
        'sections' => [
            'summary' => '[TODO-ar] Summary',
            'timeline' => '[TODO-ar] Timeline',
            'root_cause' => '[TODO-ar] Root cause',
            'contributing_factors' => '[TODO-ar] Contributing factors',
            'customer_impact' => '[TODO-ar] Customer impact',
            'action_items' => '[TODO-ar] Action items',
            'lessons_learned' => '[TODO-ar] Lessons learned',
        ],
    ],
    'dependency' => [
        'status_up' => '[TODO-ar] Up',
        'status_degraded' => '[TODO-ar] Degraded',
        'status_down' => '[TODO-ar] Down',
    ],
];
