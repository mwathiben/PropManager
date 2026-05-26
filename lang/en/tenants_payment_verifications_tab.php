<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page payment-verifications tab. Mirror en/sw/ar.
 */
return [
    'filters' => [
        'search_placeholder' => 'Search payment verifications...',
        'all_status' => 'All Status',
        'clear' => 'Clear filters',
    ],
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'table' => [
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'amount' => 'Amount',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
    'actions' => [
        'view' => 'View',
    ],
    'empty' => [
        'title' => 'No payment verifications',
        'description_filtered' => 'Try adjusting your filters.',
        'description_default' => 'Payment verifications will appear here.',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} results',
    ],
    'unknown' => 'Unknown',
    'unit_prefix' => 'Unit {number}',
];
