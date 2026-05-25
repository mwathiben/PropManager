<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: manual payment-recording form. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Record Payment',
    'back' => 'Back to Payments',
    'heading' => 'Record Payment',
    'subheading' => 'Manually record a payment from a tenant',
    'success' => [
        'title' => 'Payment Recorded!',
        'body' => 'The payment has been successfully recorded.',
        'view_payments' => 'View Payments',
    ],
    'tenant' => [
        'section' => 'Tenant Selection',
        'change' => 'Change',
        'search_placeholder' => 'Search tenant by name, phone, or unit number...',
        'no_unit' => 'No unit',
        'required' => 'Please select a tenant',
    ],
    'invoice' => [
        'section' => 'Invoice Selection',
        'loading' => 'Loading invoices...',
        'unallocated' => 'Unallocated payment (not linked to specific invoice)',
        'none' => 'No outstanding invoices for this tenant',
        'due' => 'Due: {date}',
        'due_na' => 'N/A',
        'total_outstanding' => 'Total outstanding:',
        'required' => 'Please select an invoice or mark as unallocated',
    ],
    'details' => [
        'section' => 'Payment Details',
        'amount' => 'Amount *',
        'full' => 'Full',
        'method' => 'Payment Method *',
        'date' => 'Payment Date *',
        'reference' => 'Reference (optional)',
        'reference_placeholder' => 'Receipt/transaction ID',
        'notes' => 'Notes (optional)',
        'notes_placeholder' => 'Any additional notes...',
    ],
    'overpayment' => [
        'title' => 'Overpayment detected',
        'body' => "This amount exceeds the invoice balance by {amount}. The excess will be credited to the tenant's wallet.",
    ],
    'summary' => [
        'invoice_balance' => 'Invoice Balance',
        'payment_amount' => 'Payment Amount',
        'remaining' => 'Remaining',
    ],
    'cancel' => 'Cancel',
    'submit' => 'Record Payment',
    'submitting' => 'Recording...',
];
