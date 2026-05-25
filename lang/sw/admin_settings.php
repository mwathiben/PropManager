<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin system settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Mipangilio ya Mfumo',
    'subtitle' => 'Sanidi lango la malipo kwa malipo ya usajili',
    'back_to_dashboard' => 'Rudi kwenye Dashibodi',
    'email_sms' => [
        'heading' => 'Usanidi wa Barua Pepe na SMS',
        'intro' => 'Mipangilio ya watoa huduma za barua pepe na SMS sasa inasanidiwa katika',
        'notification_center' => 'Kituo cha Arifa',
        'location' => 'chini ya Shughuli > Arifa > Mipangilio.',
    ],
    'gateway' => [
        'title' => 'Lango la Malipo (Paystack)',
        'subtitle' => 'Sanidi Paystack kwa malipo ya usajili',
        'configured' => 'Imesanidiwa',
        'not_configured' => 'Haijasanidiwa',
    ],
    'form' => [
        'public_key' => 'Ufunguo wa Umma',
        'public_key_hint' => 'Acha tupu ili kubaki na ufunguo wa sasa',
        'secret_key' => 'Ufunguo wa Siri',
    ],
    'actions' => [
        'testing' => 'Inajaribu...',
        'test_connection' => 'Jaribu Muunganisho',
        'saving' => 'Inahifadhi...',
        'save' => 'Hifadhi Mabadiliko',
    ],
    'errors' => [
        'secret_key_required' => 'Tafadhali weka ufunguo wako wa siri kwanza',
        'connection_failed' => 'Muunganisho umeshindwa: {message}',
    ],
];
