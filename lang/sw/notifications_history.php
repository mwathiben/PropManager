<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications history tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Tafuta kwa mpokeaji au mada...',
    'clear' => 'Futa',
    'status_options' => [
        'all' => 'Hali Zote',
        'pending' => 'Inasubiri',
        'sent' => 'Imetumwa',
        'delivered' => 'Imefikishwa',
        'read' => 'Imesomwa',
        'failed' => 'Imeshindwa',
    ],
    'channel_options' => [
        'all' => 'Njia Zote',
        'email' => 'Barua pepe',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'push' => 'Arifa',
    ],
    'type_options' => [
        'all' => 'Aina Zote',
        'rent_reminder' => 'Kikumbusho cha Kodi',
        'arrears_notice' => 'Taarifa ya Madeni',
        'invoice' => 'Ankara',
        'receipt' => 'Risiti',
        'rent_hike' => 'Ongezeko la Kodi',
        'lease_expiry' => 'Mwisho wa Mkataba',
        'general' => 'Ujumla',
    ],
    'table' => [
        'channel' => 'Njia',
        'recipient' => 'Mpokeaji',
        'subject' => 'Mada',
        'type' => 'Aina',
        'status' => 'Hali',
        'sent_at' => 'Imetumwa',
        'actions' => 'Vitendo',
    ],
    'unknown' => 'Haijulikani',
    'actions' => [
        'view_details' => 'Tazama Maelezo',
        'resend' => 'Tuma Tena',
    ],
    'empty' => [
        'title' => 'Hakuna Arifa Zilizopatikana',
        'filtered' => 'Jaribu kurekebisha vichujio vyako',
        'default' => 'Arifa zitaonekana hapa zikishatumwa',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya matokeo {total}',
    ],
    'detail' => [
        'title' => 'Maelezo ya Arifa',
        'subject' => 'Mada',
        'message' => 'Ujumbe',
        'type' => 'Aina',
        'channel' => 'Njia',
        'sent_at' => 'Imetumwa',
        'delivered_at' => 'Imefikishwa',
        'error' => 'Hitilafu',
    ],
    'close' => 'Funga',
    'confirm' => [
        'resend' => 'Tuma tena arifa hii?',
    ],
];
