<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone refund-creation page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Process Refund',
    'back_to_refunds' => 'Back to Refunds',
    'heading' => 'Process Refund',
    'subheading' => 'Create a refund request for a tenant payment',
    'success' => [
        'title' => 'Refund Request Created!',
        'body' => 'The refund has been submitted for processing.',
        'view_refunds' => 'View Refunds',
    ],
    'tenant_selection' => 'Tenant Selection',
    'change' => 'Change',
    'search_placeholder' => 'Search tenant by name, phone, or unit number...',
    'no_unit' => 'No unit',
    'payment_selection' => 'Payment Selection',
    'loading_payments' => 'Loading payments...',
    'no_refundable_payments' => 'No refundable payments found for this tenant',
    'invoice_prefix' => 'Invoice:',
    'of_amount' => 'of {amount}',
    'refund_details' => 'Refund Details',
    'amount_label' => 'Amount *',
    'amount_placeholder' => '0.00',
    'max' => 'Max',
    'max_refundable' => 'Max refundable: {amount}',
    'refund_method_label' => 'Refund Method *',
    'reason_label' => 'Reason *',
    'select_reason' => 'Select a reason...',
    'specify_reason_label' => 'Specify Reason *',
    'custom_reason_placeholder' => 'Enter the reason for this refund...',
    'notes_label' => 'Notes (optional)',
    'notes_placeholder' => 'Any additional notes...',
    'original_payment' => 'Original Payment',
    'already_refunded' => 'Already Refunded',
    'this_refund' => 'This Refund',
    'cancel' => 'Cancel',
    'processing' => 'Processing...',
    'create_refund' => 'Create Refund',
    'payment_methods' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'mobile_money' => 'M-Pesa',
        'paystack' => 'Paystack (Online)',
    ],
    'errors' => [
        'select_tenant' => 'Please select a tenant',
        'select_payment' => 'Please select a payment to refund',
        'valid_amount' => 'Please enter a valid amount',
        'amount_exceeds' => 'Amount cannot exceed {amount}',
        'select_reason' => 'Please select a reason',
        'specify_reason' => 'Please specify the reason',
        'select_method' => 'Please select a refund method',
    ],
];
