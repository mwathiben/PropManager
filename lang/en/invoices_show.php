<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone invoice detail page
 * (resources/js/Pages/Invoices/Show.vue). Mirror en/sw/ar.
 */
return [
    'head_title' => 'Invoice {number}',
    'page_title' => 'Invoice {number}',
    'tenant_unit' => '{tenant} - Unit {unit}',
    'legal_hold' => 'Legal hold',
    'hold_history' => 'Hold history',
    'total_due' => 'Total Due',
    'amount_paid' => 'Amount Paid',
    'remaining_balance' => 'Remaining Balance',
    'due_date' => 'Due Date',
    'billing_period' => 'Billing Period',
    'billing_period_range' => '{start} - {end}',
    'payment_progress' => 'Payment Progress',
    'paid_amount' => 'Paid: {amount}',
    'total_amount' => 'Total: {amount}',
    'line_items' => 'Line Items',
    'rent' => 'Rent',
    'water_charges' => 'Water Charges',
    'previous_arrears' => 'Previous Arrears',
    'payment_history' => 'Payment History',
    'payment_meta' => '{method} - {date}',
    'reference' => 'Ref: {reference}',
    'generating_pdf' => 'Generating PDF...',
    'status' => [
        'draft' => 'draft',
        'sent' => 'sent',
        'partial' => 'partial',
        'paid' => 'paid',
        'overdue' => 'overdue',
        'voided' => 'voided',
    ],
    'payment_methods' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'mobile_money' => 'Mobile Money',
    ],
    'actions' => [
        'preview_pdf' => 'Preview PDF',
        'download_pdf' => 'Download PDF',
        'downloading' => 'Generating...',
        'mark_sent' => 'Mark as Sent',
        'send_reminder' => 'Send Reminder',
        'record_payment' => 'Record Payment',
        'void_invoice' => 'Void Invoice',
        'reissue_invoice' => 'Reissue Invoice',
    ],
    'payment_modal' => [
        'title' => 'Record Payment',
        'amount' => 'Amount',
        'payment_method' => 'Payment Method',
        'reference_optional' => 'Reference (Optional)',
        'cancel' => 'Cancel',
        'submit' => 'Record Payment',
    ],
    'void_modal' => [
        'title' => 'Void Invoice',
        'warning' => 'Are you sure you want to void this invoice? This action cannot be undone.',
        'reason_label' => 'Reason for voiding',
        'reason_placeholder' => 'Enter reason...',
        'cancel' => 'Cancel',
        'submit' => 'Void Invoice',
    ],
];
