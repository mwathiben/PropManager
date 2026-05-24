<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub notifications tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'Kamilisha Usanidi Wako',
        'body' => 'Sanidi arifa za SMS, WhatsApp, au Push ili kuwafikia wapangaji kupitia njia mbalimbali.',
        'go_to_settings' => 'Nenda kwenye Mipangilio',
    ],
    'stats' => [
        'total_sent' => 'Jumla Zilizotumwa',
        'pending' => 'Zinazosubiri',
        'failed' => 'Zilizoshindwa',
        'this_month' => 'Mwezi Huu',
    ],
    'quick_actions' => [
        'heading' => 'Vitendo vya Haraka',
        'send' => [
            'title' => 'Tuma Arifa',
            'subtitle' => 'Tuma kwa mpangaji maalum',
        ],
        'bulk' => [
            'title' => 'Tuma kwa Wingi',
            'subtitle' => 'Tuma kwa wapangaji wengi',
        ],
        'rent_reminders' => [
            'title' => 'Tuma Vikumbusho vya Kodi',
            'subtitle' => 'Wajulishe wapangaji wote kuhusu kodi inayokuja',
        ],
        'arrears_notices' => [
            'title' => 'Tuma Taarifa za Madeni',
            'subtitle' => 'Wajulishe wapangaji wenye salio ambalo halijalipwa',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'Mgawanyo wa Njia',
        'empty' => 'Hakuna arifa zilizotumwa bado',
    ],
    'recent_activity' => [
        'heading' => 'Shughuli za Hivi Karibuni',
        'view_all' => 'Ona Zote →',
        'empty_title' => 'Hakuna arifa bado',
        'empty_subtitle' => 'Tuma arifa yako ya kwanza ili kuanza',
        'recipient' => 'Kwa: {name}',
        'unknown_recipient' => 'Haijulikani',
    ],
    'full_center' => [
        'title' => 'Kituo Kamili cha Arifa',
        'subtitle' => 'Simamia violezo, ratiba, mipangilio, na uone historia kamili',
        'open' => 'Fungua Kituo',
    ],
    'types' => [
        'rent_reminder' => 'Kikumbusho cha Kodi',
        'arrears_notice' => 'Taarifa ya Madeni',
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
    'confirm' => [
        'rent_reminders' => 'Tuma vikumbusho vya kodi kwa wapangaji wote wenye mikataba inayoendelea?',
        'arrears_notices' => 'Tuma taarifa za madeni kwa wapangaji wote wenye salio ambalo halijalipwa?',
    ],
];
