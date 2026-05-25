<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notification message-templates tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Violezo vya Arifa',
    'subheading' => 'Tengeneza violezo vinavyoweza kutumika tena kwa aina tofauti za arifa',
    'create' => 'Tengeneza Kiolezo',
    'empty' => [
        'title' => 'Hakuna Violezo Bado',
        'body' => 'Tengeneza kiolezo chako cha kwanza cha arifa ili kuanza',
    ],
    'default_badge' => 'Chaguo-msingi',
    'status' => [
        'active' => 'Inatumika',
        'inactive' => 'Haitumiki',
    ],
    'actions' => [
        'preview' => 'Onyesho la awali',
        'edit' => 'Hariri',
        'duplicate' => 'Nakili',
        'delete' => 'Futa',
        'cancel' => 'Ghairi',
    ],
    'modal' => [
        'edit_title' => 'Hariri Kiolezo',
        'create_title' => 'Tengeneza Kiolezo',
        'name_label' => 'Jina la Kiolezo',
        'name_placeholder' => 'mf., Kikumbusho cha Kodi cha Kila Mwezi',
        'type_label' => 'Aina',
        'placeholders_title' => 'Vishikilizi Vinavyopatikana',
        'placeholders_hint' => 'Bofya ili kuingiza kwenye mada au mwili',
        'subject_label' => 'Mada',
        'subject_placeholder' => "mf., Kikumbusho cha Kodi kwa {'{{unit_name}}'}",
        'body_label' => 'Mwili wa Ujumbe',
        'body_placeholder' => "Mpendwa {'{{tenant_name}}'},\n\nHiki ni kikumbusho kwamba kodi yako ya {'{{rent_amount}}'} inatakiwa kulipwa tarehe {'{{due_date}}'}.\n\nWasalaam,\n{'{{landlord_name}}'}",
        'is_active' => 'Kiolezo kinatumika',
        'update_submit' => 'Sasisha Kiolezo',
        'create_submit' => 'Tengeneza Kiolezo',
    ],
    'preview' => [
        'title' => 'Onyesho la Awali la Kiolezo',
        'subject' => 'Mada',
        'message' => 'Ujumbe',
        'note' => 'Onyesho la awali linatumia data ya sampuli. Thamani halisi zitabadilishwa wakati wa kutuma.',
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
    'confirm_delete' => 'Una uhakika unataka kufuta "{name}"?',
    'copy_suffix' => ' (Nakala)',
    'sample' => [
        'tenant_name' => 'John Doe',
        'unit_name' => 'Kitengo A1',
        'payment_method' => 'M-Pesa',
        'landlord_name' => 'Meneja wa Mali',
        'property_name' => 'Sunrise Apartments',
    ],
];
