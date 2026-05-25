<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: record manual payment modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Payment Recorded!',
    'success_body' => 'The payment has been successfully recorded.',
    'heading' => 'Record Payment',
    'invoice_label' => 'Invoice',
    'select_invoice' => 'Select an invoice',
    'amount_label' => 'Amount',
    'full_amount' => 'Full Amount',
    'balance_due' => 'Balance due: {amount}',
    'payment_method' => 'Payment Method',
    'payment_date' => 'Payment Date',
    'reference_label' => 'Reference (optional)',
    'reference_placeholder' => 'e.g., Receipt number, transaction ID',
    'notes_label' => 'Notes (optional)',
    'notes_placeholder' => 'Any additional notes...',
    'cancel' => 'Cancel',
    'recording' => 'Recording...',
    'errors' => [
        'select_invoice' => 'Please select an invoice',
        'amount_exceeds' => 'Amount cannot exceed {max}',
        'failed' => 'Failed to record payment',
    ],
];
