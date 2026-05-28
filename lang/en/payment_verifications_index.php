<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone PaymentVerifications/Index page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'Payment Verifications',
    'header' => [
        'title' => 'Payment Verifications',
        'subtitle' => 'Review and approve new tenant payments',
        'awaiting_review_badge' => '{count} awaiting review',
    ],
    'filters' => [
        'search_placeholder' => 'Search by tenant name...',
    ],
    'status_options' => [
        'all' => 'All Statuses',
        'awaiting_review' => 'Awaiting Review',
        'pending_payment' => 'Pending Payment',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
    ],
    'table' => [
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'total_required' => 'Total Required',
        'status' => 'Status',
        'submitted' => 'Submitted',
        'documents' => 'Documents',
        'actions' => 'Actions',
    ],
    'unknown_tenant' => 'Unknown',
    'actions' => [
        'view' => 'View',
    ],
    'empty' => 'No payment verifications found',
];
