<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: account-settings notifications tab. Mirror en/sw/ar.
 */
return [
    'header' => [
        'title' => 'Mipangilio Chaguomsingi ya Arifa',
        'subtitle' => 'Weka mapendeleo chaguomsingi ya arifa kwa wapangaji wapya. Wapangaji binafsi wanaweza kuyabadilisha.',
        'notification_center' => 'Kituo cha Arifa',
    ],
    'channels' => [
        'heading' => 'Njia Chaguomsingi',
        'prompt' => 'Ni njia zipi ziwezeshwe kwa chaguomsingi kwa wapangaji wapya?',
        'primary' => 'Kuu',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'email' => 'Barua pepe',
    ],
    'types' => [
        'heading' => 'Aina za Arifa',
        'prompt' => 'Ni aina zipi za arifa ziwezeshwe kwa chaguomsingi?',
        'rent_reminder' => [
            'label' => 'Vikumbusho vya Kodi',
            'description' => 'Kumbusha wapangaji kabla ya kodi kuwadia',
        ],
        'arrears_notice' => [
            'label' => 'Notisi za Madeni',
            'description' => 'Arifu wapangaji kuhusu salio ambalo bado halijalipwa',
        ],
        'invoice' => [
            'label' => 'Arifa za Ankara',
            'description' => 'Tuma maelezo ya ankara kwa wapangaji',
        ],
        'receipt' => [
            'label' => 'Risiti za Malipo',
            'description' => 'Thibitisha malipo yaliyopokelewa',
        ],
        'rent_hike' => [
            'label' => 'Marekebisho ya Kodi',
            'description' => 'Arifu wapangaji kuhusu mabadiliko ya kodi',
        ],
        'lease_expiry' => [
            'label' => 'Mwisho wa Mkataba',
            'description' => 'Tahadharisha wapangaji mkataba unapokaribia kuisha',
        ],
        'maintenance_notice' => [
            'label' => 'Notisi za Matengenezo',
            'description' => 'Sasisho za matengenezo ya jengo',
        ],
        'general' => [
            'label' => 'Sasisho za Jumla',
            'description' => 'Mawasiliano mengine muhimu',
        ],
    ],
    'timing' => [
        'heading' => 'Muda',
        'days_before_label' => 'Tuma vikumbusho vya kodi siku ngapi kabla ya tarehe ya kulipa?',
        'days_before_suffix' => 'siku kabla',
    ],
    'info' => [
        'title' => 'Hizi ni mipangilio chaguomsingi',
        'body_lead' => 'Wapangaji binafsi wanaweza kubinafsisha mapendeleo yao ya arifa. Kwa mipangilio ya kina kama violezo na ratiba, tembelea',
        'notification_center' => 'Kituo cha Arifa',
    ],
    'save' => 'Hifadhi Mipangilio Chaguomsingi ya Arifa',
    'saving' => 'Inahifadhi...',
];
