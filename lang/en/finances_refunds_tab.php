<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub refunds tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Search refunds...',
    'actions' => [
        'process_refund' => 'Process Refund',
        'view' => 'View',
    ],
    'columns' => [
        'payment_ref' => 'Payment Ref',
        'tenant' => 'Tenant',
        'amount' => 'Amount',
        'reason' => 'Reason',
        'status' => 'Status',
        'requested' => 'Requested',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Unknown',
        'no_unit' => 'N/A',
    ],
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ],
    'empty' => [
        'title' => 'No refunds found',
        'description' => 'Refund requests will appear here',
    ],
];
