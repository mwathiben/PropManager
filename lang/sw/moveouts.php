<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out detail/settlement page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'Kuondoka: {name}',
        'title' => 'Mchakato wa Kuondoka',
        'unit_prefix' => 'Chumba',
        'cancel_process' => 'Ghairi Mchakato wa Kuondoka',
        'status_label' => [
            'notice_given' => 'Notisi Imetolewa',
            'inspection_pending' => 'Ukaguzi Unaendelea',
            'inspection_complete' => 'Ukaguzi Umekamilika',
            'settlement_pending' => 'Malipo Yanasubiri',
            'completed' => 'Imekamilika',
            'cancelled' => 'Imeghairiwa',
        ],
        'steps' => [
            'notice' => 'Notisi',
            'move_out' => 'Kuondoka',
            'inspection' => 'Ukaguzi',
            'settlement' => 'Malipo',
            'complete' => 'Kamilisha',
        ],
        'start_inspection' => [
            'heading' => 'Anza Ukaguzi',
            'description' => 'Pale mpangaji anapoondoka kwenye chumba, weka tarehe halisi ya kuondoka ili kuanza mchakato wa ukaguzi.',
            'date_label' => 'Tarehe Halisi ya Kuondoka',
            'button' => 'Anza Ukaguzi',
            'starting' => 'Inaanza...',
        ],
        'deductions' => [
            'heading' => 'Ukaguzi na Makato',
            'add' => 'Ongeza Kato',
            'auto' => 'Otomatiki',
            'empty' => 'Hakuna makato yaliyorekodiwa',
            'total' => 'Jumla ya Makato',
            'edit_aria' => 'Hariri kato',
            'delete_aria' => 'Futa kato',
        ],
        'inspection_notes' => [
            'heading' => 'Maelezo ya Ukaguzi',
            'placeholder' => 'Rekodi maelezo yoyote kutoka kwa ukaguzi...',
            'button' => 'Kamilisha Ukaguzi',
            'completing' => 'Inakamilisha...',
        ],
        'settlement_ready' => [
            'heading' => 'Tayari kwa Malipo',
            'description' => 'Ukaguzi umekamilika. Kagua muhtasari wa kifedha na lipa amana.',
            'button' => 'Lipa Amana na Kamilisha',
        ],
        'completed' => [
            'heading' => 'Kuondoka Kumekamilika',
            'settled_via' => 'Imelipwa tarehe {date} kupitia {method}',
            'reference' => 'Kumbukumbu: {reference}',
            'processed_by' => 'Imeshughulikiwa na: {name}',
        ],
        'financial' => [
            'heading' => 'Muhtasari wa Kifedha',
            'deposit_held' => 'Amana Iliyoshikiliwa',
            'arrears_balance' => 'Salio la Madeni',
            'total_deductions' => 'Jumla ya Makato',
            'refund_amount' => 'Kiasi cha Kurudishwa',
        ],
        'details' => [
            'heading' => 'Maelezo',
            'notice_date' => 'Tarehe ya Notisi',
            'intended_move_out' => 'Kuondoka Kulivyokusudiwa',
            'actual_move_out' => 'Kuondoka Halisi',
        ],
        'confirm' => [
            'delete_deduction' => 'Una uhakika unataka kuondoa kato hili?',
            'cancel_moveout' => 'Una uhakika unataka kughairi kuondoka huku? Mpangaji atabaki kwenye chumba.',
        ],
        'deduction_modal' => [
            'edit_title' => 'Hariri Kato',
            'add_title' => 'Ongeza Kato',
            'category_label' => 'Aina (Hiari)',
            'custom_option' => 'Kato Maalum',
            'description_label' => 'Maelezo *',
            'description_placeholder' => 'mf., Ukarabati wa uharibifu wa ukuta',
            'amount_label' => 'Kiasi ({currency}) *',
            'notes_label' => 'Madokezo (Hiari)',
            'cancel' => 'Ghairi',
            'saving' => 'Inahifadhi...',
            'update' => 'Sasisha',
            'add_button' => 'Ongeza Kato',
        ],
        'settlement_modal' => [
            'title' => 'Kamilisha Malipo',
            'refund_to_tenant' => 'Kurudishwa kwa Mpangaji',
            'method_label' => 'Njia ya Malipo *',
            'method_cash' => 'Pesa Taslimu',
            'method_bank_transfer' => 'Uhamisho wa Benki',
            'method_mobile_money' => 'Pesa za Simu (M-Pesa)',
            'method_offset' => 'Kupunguza Dhidi ya Madeni',
            'reference_label' => 'Nambari ya Kumbukumbu (Hiari)',
            'reference_placeholder' => 'Kitambulisho cha muamala au nambari ya risiti',
            'warning' => 'Kitendo hiki kitamaliza mkataba na kuweka chumba kuwa tupu.',
            'cancel' => 'Ghairi',
            'processing' => 'Inashughulikia...',
            'complete' => 'Kamilisha Kuondoka',
        ],
    ],
];
