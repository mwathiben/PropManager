<?php

declare(strict_types=1);

return [
    'incident' => [
        'opened' => 'Tukio limefunguliwa.',
        'status_updated' => 'Hali ya tukio imebadilishwa.',
        'status_open' => 'Wazi',
        'status_investigating' => 'Inachunguzwa',
        'status_mitigated' => 'Imepunguzwa',
        'status_resolved' => 'Imetatuliwa',
        'severity_sev1' => 'SEV1 — muhimu sana, ita sasa hivi',
        'severity_sev2' => 'SEV2 — kubwa, ita on-call',
        'severity_sev3' => 'SEV3 — ndogo, tuma barua kwa on-call',
        'severity_sev4' => 'SEV4 — taarifa',
    ],
    'alert' => [
        'acknowledge_button' => 'Thibitisha',
        'acknowledged_success' => 'Tahadhari imethibitishwa.',
    ],
    'post_mortem' => [
        'heading' => 'Uchunguzi Bila Lawama',
        'sections' => [
            'summary' => 'Muhtasari',
            'timeline' => 'Mfululizo wa matukio',
            'root_cause' => 'Chanzo cha msingi',
            'contributing_factors' => 'Sababu zilizochangia',
            'customer_impact' => 'Athari kwa mteja',
            'action_items' => 'Mambo ya kufanya',
            'lessons_learned' => 'Mafunzo tuliyojifunza',
        ],
    ],
    'dependency' => [
        'status_up' => 'Inafanya kazi',
        'status_degraded' => 'Imedhoofika',
        'status_down' => 'Imezimwa',
    ],
];
