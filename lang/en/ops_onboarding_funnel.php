<?php

declare(strict_types=1);

/**
 * i18n: ops dashboard onboarding-funnel analytics page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Onboarding funnel',
    'header_title' => 'Onboarding funnel',
    'header_subtitle' => 'Per-role step completion + invite conversion (platform-wide)',
    'sessions_count' => '{count} sessions',
    'complete_rate' => '{rate}% complete',
    'biggest_drop_at_step' => 'biggest drop at step {step}',
    'invitation_funnel' => 'Invitation funnel',
    'acceptance_rate_label' => 'Acceptance rate:',
    'roles' => [
        'landlord' => 'Landlord',
        'caretaker' => 'Caretaker',
        'tenant' => 'Tenant',
        'water_client' => 'Water Client',
    ],
    'invite' => [
        'sent' => 'Sent',
        'viewed' => 'Viewed',
        'accepted' => 'Accepted',
        'pending' => 'Pending',
        'expired' => 'Expired',
    ],
    'step_labels' => [
        // Dynamic keys keyed by server-supplied label; raw label is used as fallback.
    ],
];
