<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub deposits tab. Mirror en/sw/ar.
 */
return [
    'metric' => [
        'total' => 'Total Deposits',
        'held' => 'Currently Held',
        'refunded' => 'Refunded',
        'forfeited' => 'Forfeited',
    ],
    'search_placeholder' => 'Search deposits...',
    'empty' => [
        'title' => 'No deposits found',
        'description' => 'Security deposits will appear here',
    ],
    'column' => [
        'tenant' => 'Tenant',
        'unit' => 'Unit',
        'amount' => 'Amount',
        'status' => 'Status',
        'collected' => 'Collected',
    ],
    'status' => [
        'held' => 'Held',
        'refunded' => 'Refunded',
        'forfeited' => 'Forfeited',
        'partial_refund' => 'Partial Refund',
    ],
    'status_label' => [
        'held' => 'Held',
        'refunded' => 'Refunded',
        'forfeited' => 'Forfeited',
        'partial' => 'Partial',
    ],
    'refunded_amount' => 'Refunded: {amount}',
    'processed' => 'Processed: {date}',
    'action' => [
        'refund' => 'Refund Deposit',
        'forfeit' => 'Forfeit Deposit',
    ],
    'transaction_history' => 'Transaction History',
    'transaction_history_for' => 'Transaction History - {tenant} ({unit})',
    'by' => 'by {name}',
];
