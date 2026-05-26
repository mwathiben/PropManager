<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub payments tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Tafuta malipo...',
    'actions' => [
        'record_payment' => 'Rekodi Malipo',
        'bulk_import' => 'Ingiza kwa Wingi',
        'download_receipt' => 'Pakua Risiti',
        'refund' => 'Rejesha',
    ],
    'columns' => [
        'reference' => 'Rejea',
        'tenant' => 'Mpangaji',
        'invoice' => 'Ankara',
        'amount' => 'Kiasi',
        'method' => 'Njia',
        'date' => 'Tarehe',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Haijulikani',
        'no_unit' => 'Haipatikani',
    ],
    'empty' => [
        'title' => 'Hakuna malipo yaliyopatikana',
        'description' => 'Malipo yataonekana hapa baada ya kurekodiwa',
    ],
];
