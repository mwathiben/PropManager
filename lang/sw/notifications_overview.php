<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications hub overview tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'Kamilisha Usanidi Wako',
        'body' => 'Sanidi arifa za SMS, WhatsApp, au Push ili kuwafikia wapangaji kupitia njia mbalimbali.',
        'run_wizard' => 'Endesha Mchawi wa Usanidi',
    ],
    'stats' => [
        'total_sent' => 'Jumla Zilizotumwa',
        'pending' => 'Zinasubiri',
        'failed' => 'Zilizoshindwa',
        'this_month' => 'Mwezi Huu',
    ],
    'quick_actions' => [
        'heading' => 'Vitendo vya Haraka',
        'send' => [
            'title' => 'Tuma Arifa',
            'description' => 'Tuma kwa mpangaji mahususi',
        ],
        'bulk' => [
            'title' => 'Tuma kwa Wingi',
            'description' => 'Tuma kwa wapangaji wengi',
        ],
        'rent_reminders' => [
            'title' => 'Tuma Vikumbusho vya Kodi',
            'description' => 'Wajulishe wapangaji wote kuhusu kodi inayokuja',
        ],
        'arrears_notices' => [
            'title' => 'Tuma Notisi za Madeni',
            'description' => 'Wajulishe wapangaji wenye salio linalodaiwa',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'Mgawanyo wa Njia',
        'empty' => 'Hakuna arifa zilizotumwa bado',
    ],
    'recent_activity' => [
        'heading' => 'Shughuli za Hivi Karibuni',
        'empty_title' => 'Hakuna arifa bado',
        'empty_body' => 'Tuma arifa yako ya kwanza ili kuanza',
        'recipient' => 'Kwa: {name}',
        'unknown_recipient' => 'Haijulikani',
    ],
    'confirm' => [
        'rent_reminders' => 'Tuma vikumbusho vya kodi kwa wapangaji wote wenye mikataba inayoendelea?',
        'arrears_notices' => 'Tuma notisi za madeni kwa wapangaji wote wenye salio linalodaiwa?',
    ],
    'types' => [
        'rent_reminder' => 'Kikumbusho cha Kodi',
        'arrears_notice' => 'Notisi ya Madeni',
        'invoice' => 'Ankara',
        'receipt' => 'Risiti',
        'rent_hike' => 'Ongezeko la Kodi',
        'lease_expiry' => 'Mwisho wa Mkataba',
        'general' => 'Jumla',
    ],
    'channels' => [
        'email' => 'Barua pepe',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Push',
    ],
];
