<?php

declare(strict_types=1);

return [
    'page_title' => 'Credit Notes',
    'header_title' => 'Credit Notes',
    'header_subtitle' => 'Issue and manage tenant account credits',
    'breadcrumb' => [
        'finance_hub' => 'Finance Hub',
        'credit_notes' => 'Credit Notes',
    ],
    'stats' => [
        'total' => 'Total',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'applied' => 'Applied',
        'total_amount' => 'Total Amount',
    ],
    'filters' => [
        'search_placeholder' => 'Search credit notes...',
        'all_statuses' => 'All Statuses',
    ],
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'applied' => 'Applied',
        'voided' => 'Voided',
    ],
    'actions' => [
        'issue' => 'Issue Credit Note',
        'view' => 'View',
    ],
    'table' => [
        'credit_number' => 'Credit #',
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'amount' => 'Amount',
        'reason' => 'Reason',
        'status' => 'Status',
        'date' => 'Date',
        'actions' => 'Actions',
        'applied_amount' => 'Applied: {amount}',
    ],
    'empty' => [
        'title' => 'No credit notes found',
        'subtitle' => 'Issue a credit note to adjust tenant balances',
    ],
];
