<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Mialiko ya Wapangaji',
    'subtitle' => 'Alika wapangaji wapya kwenye mali zako',
    'send' => 'Tuma Mwaliko',
    'no_vacant' => [
        'title' => 'Hakuna Vyumba Tupu',
        'body' => 'Vyumba vyako vyote vimekaliwa. Toa chumba au ongeza vyumba vipya ili kutuma mialiko ya wapangaji.',
    ],
    'stats' => [
        'total' => 'Jumla ya Mialiko',
        'pending' => 'Inasubiri',
        'accepted' => 'Imekubaliwa',
    ],
    'status' => [
        'pending' => 'Inasubiri',
        'accepted' => 'Imekubaliwa',
        'expired' => 'Imeisha muda',
    ],
    'table' => [
        'tenant' => 'Mpangaji',
        'unit' => 'Chumba',
        'lease_terms' => 'Masharti ya Mkataba',
        'status' => 'Hali',
        'actions' => 'Vitendo',
    ],
    'pending_registration' => 'Usajili Unasubiri',
    'unit_prefix' => 'Chumba',
    'per_month' => '/mwezi',
    'deposit_label' => 'Amana:',
    'start_label' => 'Anza:',
    'expires_label' => 'Inaisha:',
    'viewed' => 'Imetazamwa',
    'actions' => [
        'copy' => 'Nakili kiungo cha mwaliko',
        'resend' => 'Tuma tena mwaliko',
        'edit' => 'Hariri mwaliko',
        'cancel' => 'Ghairi mwaliko',
        'cancel_btn' => 'Ghairi',
    ],
    'empty' => [
        'title' => 'Hakuna mialiko',
        'filtered' => 'Hakuna mialiko inayolingana na kichujio hiki.',
        'get_started' => 'Anza kwa kutuma mwaliko wa mpangaji.',
    ],
    'create' => [
        'title' => 'Tuma Mwaliko wa Mpangaji',
        'sending' => 'Inatuma...',
    ],
    'edit' => [
        'title' => 'Hariri Mwaliko',
        'saving' => 'Inahifadhi...',
        'save' => 'Hifadhi Mabadiliko',
    ],
    'form' => [
        'unit' => 'Chagua Chumba *',
        'unit_placeholder' => 'Chagua chumba tupu...',
        'email' => 'Anwani ya Barua Pepe *',
        'email_placeholder' => 'mpangaji@mfano.com',
        'name' => 'Jina la Mpangaji',
        'name_placeholder' => 'John Doe',
        'phone' => 'Nambari ya Simu',
        'phone_placeholder' => '+254 712 345 678',
        'lease_terms' => 'Masharti ya Mkataba',
        'rent' => 'Kodi ya Mwezi ({currency}) *',
        'service_charge' => 'Ada ya Huduma',
        'deposit' => 'Amana ({currency}) *',
        'start_date' => 'Tarehe ya Kuanza *',
        'end_date' => 'Tarehe ya Mwisho (Hiari)',
        'total_movein' => 'Jumla ya Gharama ya Kuhamia',
        'movein_breakdown' => 'Kodi ya mwezi wa kwanza + ada ya huduma + amana',
        'send_via' => 'Tuma Mwaliko Kupitia *',
        'notification_channels' => 'Njia za Arifa',
    ],
    'channel' => [
        'email' => 'Barua pepe',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'not_configured' => '(Haijasanidiwa)',
    ],
    'confirm' => [
        'resend' => 'Tuma tena mwaliko huu?',
        'cancel' => 'Una uhakika unataka kughairi mwaliko huu? Hii haiwezi kutenduliwa.',
    ],
    'alert' => [
        'copied' => 'Kiungo cha mwaliko kimenakiliwa!',
    ],
];
