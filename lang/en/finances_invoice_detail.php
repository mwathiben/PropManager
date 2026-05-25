<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: invoice-detail slide-out modal. Mirror en/sw/ar.
 */
return [
    'title' => 'Invoice Details',
    'amount_due' => 'Amount Due',
    'payment_progress' => 'Payment Progress',
    'paid_amount' => 'Paid: {amount}',
    'total_amount' => 'Total: {amount}',
    'tenant' => 'Tenant',
    'unit' => 'Unit',
    'due_date' => 'Due Date',
    'billing_period' => 'Billing Period',
    'line_items' => 'Line Items',
    'rent' => 'Rent',
    'water_charges' => 'Water Charges',
    'previous_arrears' => 'Previous Arrears',
    'total' => 'Total',
    'payments_applied' => 'Payments Applied',
    'fetch_error' => 'Failed to fetch invoice',
    'actions' => [
        'record_payment' => 'Record Payment',
        'send_invoice' => 'Send Invoice',
        'send_reminder' => 'Send Reminder',
        'preview' => 'Preview',
        'download' => 'Download',
        'void' => 'Void',
        'reissue' => 'Reissue',
    ],
    'void' => [
        'title' => 'Void this invoice?',
        'warning' => 'This action cannot be undone. The invoice will be marked as voided.',
        'reason_placeholder' => 'Enter reason for voiding...',
        'voiding' => 'Voiding...',
        'confirm' => 'Confirm Void',
        'cancel' => 'Cancel',
    ],
];
