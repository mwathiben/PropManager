<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: deposit refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Deposit Refunded!',
    'success_body' => 'The deposit refund has been processed.',
    'heading' => 'Refund Deposit',
    'deposit_amount' => 'Deposit Amount',
    'tenant' => 'Tenant',
    'unit' => 'Unit',
    'refund_amount' => 'Refund Amount',
    'full_amount' => 'Full Amount',
    'deductions' => 'Deductions (if any)',
    'reason_label' => 'Reason for Deductions',
    'select_reason' => 'Select a reason',
    'net_refund' => 'Net Refund to Tenant',
    'cancel' => 'Cancel',
    'process_refund' => 'Process Refund',
    'processing' => 'Processing...',
    'reasons' => [
        'unpaid_rent' => 'Unpaid rent',
        'property_damage' => 'Property damage',
        'cleaning_fees' => 'Cleaning fees',
        'unpaid_utilities' => 'Unpaid utilities',
        'early_termination' => 'Early termination fee',
        'other' => 'Other',
    ],
    'errors' => [
        'amount_min' => 'Refund amount must be greater than 0',
        'amount_exceeds' => 'Refund amount cannot exceed {max}',
        'deductions_negative' => 'Deductions cannot be negative',
        'total_exceeds' => 'Refund amount plus deductions cannot exceed deposit amount',
        'reason_required' => 'Please provide a reason for deductions',
    ],
];
