<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant payment/invoice history page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Historia ya Malipo',
    'heading' => 'Historia ya Malipo',
    'subtitle' => 'Tazama malipo na ankara zako zote',
    'tabs' => [
        'payments' => 'Malipo',
        'invoices' => 'Ankara',
    ],
    'columns' => [
        'date' => 'Tarehe',
        'amount' => 'Kiasi',
        'method' => 'Njia',
        'reference' => 'Marejeo',
        'invoice_number' => 'Ankara #',
        'paid' => 'Iliyolipwa',
        'status' => 'Hali',
    ],
    'payments_empty' => [
        'title' => 'Hakuna malipo bado',
        'description' => 'Historia yako ya malipo itaonekana hapa',
    ],
    'invoices_empty' => [
        'title' => 'Hakuna ankara bado',
        'description' => 'Ankara zako zitaonekana hapa',
    ],
    'download_receipt' => 'Pakua Risiti',
];
