<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant payment/invoice history page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Payment History',
    'heading' => 'Payment History',
    'subtitle' => 'View all your payments and invoices',
    'tabs' => [
        'payments' => 'Payments',
        'invoices' => 'Invoices',
    ],
    'columns' => [
        'date' => 'Date',
        'amount' => 'Amount',
        'method' => 'Method',
        'reference' => 'Reference',
        'invoice_number' => 'Invoice #',
        'paid' => 'Paid',
        'status' => 'Status',
    ],
    'payments_empty' => [
        'title' => 'No payments yet',
        'description' => 'Your payment history will appear here',
    ],
    'invoices_empty' => [
        'title' => 'No invoices yet',
        'description' => 'Your invoices will appear here',
    ],
    'download_receipt' => 'Download Receipt',
];
