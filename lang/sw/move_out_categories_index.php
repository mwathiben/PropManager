<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out deduction categories settings page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'Aina za Makato',
    'title' => 'Aina za Makato',
    'subtitle' => 'Sanidi aina za makato kwa ukaguzi wa kuhama.',
    'add_category' => 'Ongeza Aina',

    'breadcrumbs' => [
        'move_outs' => 'Kuhama',
        'deduction_categories' => 'Aina za Makato',
    ],

    'stats' => [
        'total' => 'Jumla',
        'active' => 'Inayotumika',
        'auto_applied' => 'Inayowekwa Kiotomatiki',
        'custom' => 'Maalum',
    ],

    'search' => [
        'placeholder' => 'Tafuta aina...',
    ],

    'scope_filter' => [
        'all' => 'Mawanda Yote',
        'platform' => 'Chaguomsingi za Mfumo',
        'custom' => 'Aina Zako',
        'building' => 'Mahususi kwa Jengo',
    ],

    'empty' => [
        'title' => 'Hakuna aina zilizopatikana',
        'try_different_search' => 'Jaribu neno tofauti la utafutaji.',
        'add_first' => 'Ongeza aina yako ya kwanza ya makato ili kuanza.',
    ],

    'sections' => [
        'platform_defaults' => 'Chaguomsingi za Mfumo',
        'your_categories' => 'Aina Zako',
        'building_specific' => 'Mahususi kwa Jengo',
    ],

    'badges' => [
        'platform' => 'Mfumo',
        'read_only' => 'Soma tu',
        'all_buildings' => 'Majengo Yote',
    ],

    'card' => [
        'auto_apply' => 'Weka kiotomatiki',
        'active' => 'Inayotumika',
    ],

    'no_custom' => [
        'message' => 'Hakuna aina maalum bado.',
        'create_first' => 'Tengeneza aina yako ya kwanza',
    ],

    'modal' => [
        'title_new' => 'Aina Mpya',
        'title_edit' => 'Hariri Aina',
        'name_label' => 'Jina la Aina',
        'name_placeholder' => 'mfano, Ada ya Usafi',
        'description_label' => 'Maelezo',
        'description_placeholder' => 'Maelezo mafupi ya makato haya',
        'default_amount_label' => 'Kiasi Chaguomsingi ({currency})',
        'scope_label' => 'Mawanda',
        'all_buildings' => 'Majengo Yote',
        'always_apply_label' => 'Weka Kila Wakati',
        'always_apply_help' => 'Huongezwa kiotomatiki ukaguzi unapoanza',
        'active_label' => 'Inayotumika',
        'active_help' => 'Inapatikana kwa uteuzi',
        'cancel' => 'Ghairi',
        'saving' => 'Inahifadhi...',
        'update' => 'Sasisha',
        'create' => 'Tengeneza',
    ],

    'delete_modal' => [
        'title' => 'Futa Aina?',
        'message_before' => 'Hii itafuta kabisa',
        'message_after' => '. Makato yaliyopo yanayotumia aina hii hayataathiriwa.',
        'cancel' => 'Ghairi',
        'delete' => 'Futa',
    ],
];
