<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone invoice detail page
 * (resources/js/Pages/Invoices/Show.vue). Mirror en/sw/ar.
 */
return [
    'head_title' => 'Ankara {number}',
    'page_title' => 'Ankara {number}',
    'tenant_unit' => '{tenant} - Chumba {unit}',
    'legal_hold' => 'Zuio la kisheria',
    'hold_history' => 'Historia ya zuio',
    'total_due' => 'Jumla Inayodaiwa',
    'amount_paid' => 'Kiasi Kilicholipwa',
    'remaining_balance' => 'Salio Lililobaki',
    'due_date' => 'Tarehe ya Mwisho',
    'billing_period' => 'Kipindi cha Bili',
    'billing_period_range' => '{start} - {end}',
    'payment_progress' => 'Maendeleo ya Malipo',
    'paid_amount' => 'Imelipwa: {amount}',
    'total_amount' => 'Jumla: {amount}',
    'line_items' => 'Vipengele vya Bili',
    'rent' => 'Kodi',
    'water_charges' => 'Gharama za Maji',
    'previous_arrears' => 'Madeni ya Awali',
    'payment_history' => 'Historia ya Malipo',
    'payment_meta' => '{method} - {date}',
    'reference' => 'Kumb: {reference}',
    'generating_pdf' => 'Inatengeneza PDF...',
    'status' => [
        'draft' => 'rasimu',
        'sent' => 'imetumwa',
        'partial' => 'sehemu',
        'paid' => 'imelipwa',
        'overdue' => 'imechelewa',
        'voided' => 'imetanguliwa',
    ],
    'payment_methods' => [
        'cash' => 'Pesa Taslimu',
        'bank_transfer' => 'Uhamisho wa Benki',
        'mobile_money' => 'Pesa za Simu',
    ],
    'actions' => [
        'preview_pdf' => 'Hakiki PDF',
        'download_pdf' => 'Pakua PDF',
        'downloading' => 'Inatengeneza...',
        'mark_sent' => 'Weka Alama Imetumwa',
        'send_reminder' => 'Tuma Kikumbusho',
        'record_payment' => 'Rekodi Malipo',
        'void_invoice' => 'Tangua Ankara',
        'reissue_invoice' => 'Toa Ankara Upya',
    ],
    'payment_modal' => [
        'title' => 'Rekodi Malipo',
        'amount' => 'Kiasi',
        'payment_method' => 'Njia ya Malipo',
        'reference_optional' => 'Kumbukumbu (Hiari)',
        'cancel' => 'Ghairi',
        'submit' => 'Rekodi Malipo',
    ],
    'void_modal' => [
        'title' => 'Tangua Ankara',
        'warning' => 'Una uhakika unataka kutangua ankara hii? Kitendo hiki hakiwezi kutenduliwa.',
        'reason_label' => 'Sababu ya kutangua',
        'reason_placeholder' => 'Ingiza sababu...',
        'cancel' => 'Ghairi',
        'submit' => 'Tangua Ankara',
    ],
];
