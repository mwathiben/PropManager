<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease counter-offer negotiation history timeline.
 * Mirror en/sw/ar.
 */
return [
    'proposed_rent_label' => 'Kodi iliyopendekezwa:',
    'empty' => 'Bado hakuna historia ya pendekezo la kodi.',
    'roles' => [
        'landlord' => 'mwenye nyumba',
        'tenant' => 'mpangaji',
        'caretaker' => 'mlinzi',
    ],
    'actions' => [
        'proposed' => 'amependekeza',
        'countered' => 'amejibu kwa pendekezo',
        're_proposed' => 'amependekeza tena',
        'accepted' => 'amekubali',
        'rejected' => 'amekataa',
        'expired' => 'imeisha muda',
    ],
    'time_ago' => [
        'just_now' => 'sasa hivi',
        'minutes' => 'dakika {count} zilizopita',
        'hours' => 'saa {count} zilizopita',
        'days' => 'siku {count} zilizopita',
    ],
];
