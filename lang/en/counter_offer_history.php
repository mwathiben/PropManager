<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease counter-offer negotiation history timeline.
 * Mirror en/sw/ar.
 */
return [
    'proposed_rent_label' => 'Proposed rent:',
    'empty' => 'No counter-offer history yet.',
    'roles' => [
        'landlord' => 'landlord',
        'tenant' => 'tenant',
        'caretaker' => 'caretaker',
    ],
    'actions' => [
        'proposed' => 'proposed',
        'countered' => 'countered',
        're_proposed' => 're proposed',
        'accepted' => 'accepted',
        'rejected' => 'rejected',
        'expired' => 'expired',
    ],
    'time_ago' => [
        'just_now' => 'just now',
        'minutes' => '{count}m ago',
        'hours' => '{count}h ago',
        'days' => '{count}d ago',
    ],
];
