<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant verification conduct page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Thibitisha {name}',
    'heading' => 'Uthibitishaji wa Mpangaji',
    'verify_subtitle' => 'Thibitisha {name}',
    'manage_templates' => 'Dhibiti Violezo',
    'unit_line' => 'Chumba {number} - {building}',
    'start' => [
        'title' => 'Anza Uthibitishaji',
        'description' => 'Chagua kiolezo cha uthibitishaji ili kuanza mchakato wa uthibitishaji kwa mpangaji huyu.',
        'select_template' => 'Chagua Kiolezo',
        'choose_template' => 'Chagua kiolezo...',
        'option' => '{name} (vipengele {count})',
        'option_default_suffix' => ' - Chaguo-msingi',
        'button' => 'Anza Uthibitishaji',
        'starting' => 'Inaanza...',
        'no_templates' => 'Hakuna violezo vya uthibitishaji vilivyopatikana.',
        'create_one' => 'Tengeneza kimoja kwanza',
    ],
    'progress' => [
        'title' => 'Maendeleo ya Uthibitishaji',
        'percent_complete' => 'Imekamilika {percent}%',
    ],
    'stats' => [
        'verified' => 'Imethibitishwa',
        'waived' => 'Imeondolewa',
        'pending' => 'Inasubiri',
        'rejected' => 'Imekataliwa',
    ],
    'checklist' => [
        'title' => 'Orodha ya Uthibitishaji',
        'reset' => 'Weka upya',
    ],
    'item' => [
        'required' => 'Inahitajika',
        'note_prefix' => 'Dokezo: {note}',
        'audit' => '{action} na {name} tarehe {date}',
    ],
    'action_label' => [
        'verified' => 'Imethibitishwa',
        'waived' => 'Imeondolewa',
        'rejected' => 'Imekataliwa',
        'updated' => 'Imesasishwa',
    ],
    'title' => [
        'add_note' => 'Ongeza dokezo',
        'verify' => 'Thibitisha',
        'reject' => 'Kataa',
        'waive' => 'Ondoa',
        'reset_pending' => 'Rudisha kwa inasubiri',
    ],
    'complete' => [
        'pending_notice' => 'Vipengele {count} vinavyohitajika vinasubiri',
        'rejected_notice' => ', {count} vimekataliwa',
        'ready' => 'Vipengele vyote vinavyohitajika vimethibitishwa. Tayari kukamilisha!',
        'button' => 'Kamilisha Uthibitishaji',
    ],
    'note_modal' => [
        'title' => 'Ongeza Dokezo',
        'placeholder' => 'Ongeza dokezo kuhusu kipengele hiki cha uthibitishaji...',
        'cancel' => 'Ghairi',
        'save' => 'Hifadhi Dokezo',
    ],
    'alert' => [
        'select_template' => 'Tafadhali chagua kiolezo',
        'required_first' => 'Vipengele vyote vinavyohitajika lazima vithibitishwe au viondolewe kabla ya kukamilisha.',
    ],
    'confirm' => [
        'reset' => 'Una uhakika unataka kuweka upya uthibitishaji? Maendeleo yote yatapotea.',
        'complete' => 'Kamilisha uthibitishaji kwa mpangaji huyu? Hii itaonyesha mkataba kama umethibitishwa.',
    ],
];
