<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: scheduled-notifications tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Arifa Zilizoratibiwa',
    'subheading' => 'Ratibu vikumbusho vya kodi, notisi za madeni, na arifa za mwisho wa mkataba kiotomatiki',
    'create_schedule' => 'Unda Ratiba',
    'status' => [
        'active' => 'Inafanya kazi',
        'paused' => 'Imesimamishwa',
    ],
    'field' => [
        'type' => 'Aina',
        'trigger' => 'Kichocheo',
        'send_time' => 'Muda wa Kutuma',
        'channels' => 'Njia',
    ],
    'next' => 'Inayofuata: {value}',
    'last' => 'Mwisho: {value}',
    'action' => [
        'run_now' => 'Endesha Sasa',
        'pause' => 'Simamisha',
        'resume' => 'Endelea',
        'edit' => 'Hariri',
        'delete' => 'Futa',
        'cancel' => 'Ghairi',
    ],
    'empty' => [
        'title' => 'Bado Hakuna Ratiba',
        'body' => 'Unda ratiba za arifa za kiotomatiki ili kuwafahamisha wapangaji',
    ],
    'modal' => [
        'edit_title' => 'Hariri Ratiba',
        'create_title' => 'Unda Ratiba',
        'update' => 'Sasisha Ratiba',
        'create' => 'Unda Ratiba',
    ],
    'form' => [
        'name' => 'Jina la Ratiba',
        'name_placeholder' => 'mfano, Kikumbusho cha Kodi cha Siku 3',
        'notification_type' => 'Aina ya Arifa',
        'template' => 'Kiolezo (Hiari)',
        'use_default' => 'Tumia chaguo-msingi',
        'trigger' => 'Kichocheo',
        'days' => 'Siku',
        'send_time' => 'Muda wa Kutuma',
        'channels' => 'Njia',
        'is_active' => 'Ratiba inafanya kazi',
    ],
    'trigger_type' => [
        'days_before_due' => [
            'label' => 'Siku Kabla ya Kodi Kuwa Tayari',
            'description' => 'Tuma siku X kabla ya tarehe ya kulipa kodi',
        ],
        'days_after_overdue' => [
            'label' => 'Siku Baada ya Kuchelewa',
            'description' => 'Tuma siku X baada ya kodi kuchelewa',
        ],
        'days_before_expiry' => [
            'label' => 'Siku Kabla ya Mwisho wa Mkataba',
            'description' => 'Tuma siku X kabla ya mkataba kuisha',
        ],
    ],
    'notification_type' => [
        'rent_reminder' => 'Kikumbusho cha Kodi',
        'arrears_notice' => 'Notisi ya Madeni',
        'lease_expiry' => 'Mwisho wa Mkataba',
    ],
    'channel' => [
        'email' => 'Barua pepe',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Arifa',
    ],
    'next_run' => [
        'paused' => 'Imesimamishwa',
        'calculating' => 'Inakokotoa...',
    ],
    'last_run' => [
        'never' => 'Kamwe',
    ],
    'confirm' => [
        'delete' => 'Una uhakika unataka kufuta "{name}"?',
        'run' => 'Endesha "{name}" sasa? Hii itatuma arifa kwa wapangaji wote wanaolingana.',
    ],
];
