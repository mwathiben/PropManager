<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: manual payment-recording form. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Rekodi Malipo',
    'back' => 'Rudi kwenye Malipo',
    'heading' => 'Rekodi Malipo',
    'subheading' => 'Rekodi malipo kutoka kwa mpangaji mwenyewe',
    'success' => [
        'title' => 'Malipo Yamerekodiwa!',
        'body' => 'Malipo yamerekodiwa kwa mafanikio.',
        'view_payments' => 'Tazama Malipo',
    ],
    'tenant' => [
        'section' => 'Uteuzi wa Mpangaji',
        'change' => 'Badilisha',
        'search_placeholder' => 'Tafuta mpangaji kwa jina, simu, au nambari ya kitengo...',
        'no_unit' => 'Hakuna kitengo',
        'required' => 'Tafadhali chagua mpangaji',
    ],
    'invoice' => [
        'section' => 'Uteuzi wa Ankara',
        'loading' => 'Inapakia ankara...',
        'unallocated' => 'Malipo yasiyotengwa (hayajaunganishwa na ankara mahususi)',
        'none' => 'Hakuna ankara zilizosalia kwa mpangaji huyu',
        'due' => 'Inadaiwa: {date}',
        'due_na' => 'Haipatikani',
        'total_outstanding' => 'Jumla ya deni:',
        'required' => 'Tafadhali chagua ankara au weka alama kuwa haijatengwa',
    ],
    'details' => [
        'section' => 'Maelezo ya Malipo',
        'amount' => 'Kiasi *',
        'full' => 'Kamili',
        'method' => 'Njia ya Malipo *',
        'date' => 'Tarehe ya Malipo *',
        'reference' => 'Rejea (hiari)',
        'reference_placeholder' => 'Kitambulisho cha risiti/muamala',
        'notes' => 'Maelezo (hiari)',
        'notes_placeholder' => 'Maelezo yoyote ya ziada...',
    ],
    'overpayment' => [
        'title' => 'Malipo ya ziada yamegunduliwa',
        'body' => 'Kiasi hiki kinazidi salio la ankara kwa {amount}. Ziada itawekwa kwenye pochi ya mpangaji.',
    ],
    'summary' => [
        'invoice_balance' => 'Salio la Ankara',
        'payment_amount' => 'Kiasi cha Malipo',
        'remaining' => 'Kilichobaki',
    ],
    'cancel' => 'Ghairi',
    'submit' => 'Rekodi Malipo',
    'submitting' => 'Inarekodi...',
];
