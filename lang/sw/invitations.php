<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Mialiko ya Wasimamizi',
    'subtitle' => 'Alika na simamia wasimamizi wa mali zako',
    'send' => 'Tuma Mwaliko',
    'table' => [
        'email' => 'Barua Pepe ya Msimamizi',
        'property' => 'Mali',
        'sent_date' => 'Tarehe ya Kutuma',
        'status' => 'Hali',
        'actions' => 'Vitendo',
    ],
    'accepted_at' => 'Imekubaliwa {date}',
    'actions' => [
        'copy' => 'Nakili Kiungo',
        'copy_title' => 'Nakili kiungo cha mwaliko',
        'resend' => 'Tuma tena',
        'resend_title' => 'Tuma tena mwaliko',
        'cancel' => 'Ghairi',
        'cancel_title' => 'Ghairi mwaliko',
    ],
    'empty' => [
        'title' => 'Hakuna mialiko iliyotumwa',
        'description' => 'Anza kwa kutuma mwaliko kwa msimamizi.',
        'action' => 'Tuma Mwaliko wa Kwanza',
    ],
    'modal' => [
        'title' => 'Tuma Mwaliko wa Msimamizi',
        'email' => 'Anwani ya Barua Pepe',
        'email_placeholder' => 'msimamizi@mfano.com',
        'property' => 'Mali',
        'notice' => 'Msimamizi atapokea barua pepe yenye kiungo cha kukubali mwaliko na kutengeneza akaunti yake. Mialiko inaisha muda baada ya siku 30.',
        'cancel' => 'Ghairi',
        'sending' => 'Inatuma...',
    ],
    'toast' => [
        'title' => 'Mwaliko Umekubaliwa!',
        'message' => '{name} amekubali mwaliko wa {property}',
    ],
    'confirm' => [
        'resend' => 'Tuma tena mwaliko huu?',
        'cancel' => 'Una uhakika unataka kughairi mwaliko huu?',
    ],
    'alert' => [
        'copied' => 'Kiungo cha mwaliko kimenakiliwa!',
    ],
];
