<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub invoices tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Tafuta ankara...',
    'actions' => [
        'generate_invoices' => 'Tengeneza Ankara',
        'view' => 'Angalia',
        'record_payment' => 'Rekodi Malipo',
        'cancel' => 'Ghairi',
    ],
    'columns' => [
        'invoice_number' => 'Ankara #',
        'tenant' => 'Mpangaji',
        'unit' => 'Nyumba',
        'amount' => 'Kiasi',
        'paid' => 'Iliyolipwa',
        'status' => 'Hali',
        'due_date' => 'Tarehe ya Kulipa',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Haijulikani',
        'no_unit' => 'Haipatikani',
    ],
    'empty' => [
        'title' => 'Hakuna ankara zilizopatikana',
        'description' => 'Tengeneza ankara ili kuanza',
    ],
    'generate_modal' => [
        'title' => 'Tengeneza Ankara',
        'description' => 'Tengeneza ankara kwa mikataba yote inayotumika kwa kipindi cha malipo kilichochaguliwa.',
        'month_label' => 'Mwezi',
        'year_label' => 'Mwaka',
    ],
    'months' => [
        'january' => 'Januari',
        'february' => 'Februari',
        'march' => 'Machi',
        'april' => 'Aprili',
        'may' => 'Mei',
        'june' => 'Juni',
        'july' => 'Julai',
        'august' => 'Agosti',
        'september' => 'Septemba',
        'october' => 'Oktoba',
        'november' => 'Novemba',
        'december' => 'Desemba',
    ],
];
