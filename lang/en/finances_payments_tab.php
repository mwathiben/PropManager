<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub payments tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Search payments...',
    'actions' => [
        'record_payment' => 'Record Payment',
        'bulk_import' => 'Bulk Import',
        'download_receipt' => 'Download Receipt',
        'refund' => 'Refund',
    ],
    'columns' => [
        'reference' => 'Reference',
        'tenant' => 'Tenant',
        'invoice' => 'Invoice',
        'amount' => 'Amount',
        'method' => 'Method',
        'date' => 'Date',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Unknown',
        'no_unit' => 'N/A',
    ],
    'empty' => [
        'title' => 'No payments found',
        'description' => 'Payments will appear here once recorded',
    ],
];
