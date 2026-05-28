<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub invoices tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Search invoices...',
    'actions' => [
        'generate_invoices' => 'Generate Invoices',
        'view' => 'View',
        'record_payment' => 'Record Payment',
        'cancel' => 'Cancel',
    ],
    'columns' => [
        'invoice_number' => 'Invoice #',
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'amount' => 'Amount',
        'paid' => 'Paid',
        'status' => 'Status',
        'due_date' => 'Due Date',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Unknown',
        'no_unit' => 'N/A',
    ],
    'empty' => [
        'title' => 'No invoices found',
        'description' => 'Generate invoices to get started',
    ],
    'generate_modal' => [
        'title' => 'Generate Invoices',
        'description' => 'Generate invoices for all active leases for the selected billing period.',
        'month_label' => 'Month',
        'year_label' => 'Year',
    ],
    'months' => [
        'january' => 'January',
        'february' => 'February',
        'march' => 'March',
        'april' => 'April',
        'may' => 'May',
        'june' => 'June',
        'july' => 'July',
        'august' => 'August',
        'september' => 'September',
        'october' => 'October',
        'november' => 'November',
        'december' => 'December',
    ],
];
