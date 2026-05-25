<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: payment refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Refund Initiated!',
    'success_body' => 'The refund request has been submitted.',
    'heading' => 'Initiate Refund',
    'notice' => 'Refunds may take 3-5 business days to process depending on the payment method.',
    'payment_label' => 'Payment',
    'select_payment' => 'Select a payment',
    'already_refunded' => ' (Already Refunded)',
    'original_amount' => 'Original Amount',
    'payment_method' => 'Payment Method',
    'refund_amount' => 'Refund Amount',
    'full_amount' => 'Full Amount',
    'reason_label' => 'Reason',
    'select_reason' => 'Select a reason',
    'refund_method' => 'Refund Method',
    'cancel' => 'Cancel',
    'processing' => 'Processing...',
    'methods' => [
        'original' => 'Original Payment Method',
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'mobile_money' => 'M-Pesa',
    ],
    'reasons' => [
        'overpayment' => 'Overpayment',
        'duplicate' => 'Duplicate Payment',
        'moved_out' => 'Tenant Moved Out',
        'billing_error' => 'Billing Error',
        'service_not_provided' => 'Service Not Provided',
        'other' => 'Other',
    ],
    'errors' => [
        'select_payment' => 'Please select a payment',
        'valid_amount' => 'Please enter a valid amount',
        'amount_exceeds' => 'Amount cannot exceed {max}',
        'select_reason' => 'Please select a reason',
        'select_method' => 'Please select a refund method',
        'failed' => 'Failed to create refund',
    ],
];
