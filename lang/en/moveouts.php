<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out detail/settlement page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'Move-Out: {name}',
        'title' => 'Move-Out Process',
        'unit_prefix' => 'Unit',
        'cancel_process' => 'Cancel Move-Out Process',
        'status_label' => [
            'notice_given' => 'Notice Given',
            'inspection_pending' => 'Inspection In Progress',
            'inspection_complete' => 'Inspection Complete',
            'settlement_pending' => 'Settlement Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'steps' => [
            'notice' => 'Notice',
            'move_out' => 'Move Out',
            'inspection' => 'Inspection',
            'settlement' => 'Settlement',
            'complete' => 'Complete',
        ],
        'start_inspection' => [
            'heading' => 'Start Inspection',
            'description' => 'When the tenant has vacated the unit, enter the actual move-out date to begin the inspection process.',
            'date_label' => 'Actual Move-Out Date',
            'button' => 'Start Inspection',
            'starting' => 'Starting...',
        ],
        'deductions' => [
            'heading' => 'Inspection & Deductions',
            'add' => 'Add Deduction',
            'auto' => 'Auto',
            'empty' => 'No deductions recorded',
            'total' => 'Total Deductions',
            'edit_aria' => 'Edit deduction',
            'delete_aria' => 'Delete deduction',
        ],
        'inspection_notes' => [
            'heading' => 'Inspection Notes',
            'placeholder' => 'Record any notes from the inspection...',
            'button' => 'Complete Inspection',
            'completing' => 'Completing...',
        ],
        'settlement_ready' => [
            'heading' => 'Ready for Settlement',
            'description' => 'Inspection is complete. Review the financial summary and settle the deposit.',
            'button' => 'Settle Deposit & Complete',
        ],
        'completed' => [
            'heading' => 'Move-Out Completed',
            'settled_via' => 'Settled on {date} via {method}',
            'reference' => 'Reference: {reference}',
            'processed_by' => 'Processed by: {name}',
        ],
        'financial' => [
            'heading' => 'Financial Summary',
            'deposit_held' => 'Deposit Held',
            'arrears_balance' => 'Arrears Balance',
            'total_deductions' => 'Total Deductions',
            'refund_amount' => 'Refund Amount',
        ],
        'details' => [
            'heading' => 'Details',
            'notice_date' => 'Notice Date',
            'intended_move_out' => 'Intended Move-Out',
            'actual_move_out' => 'Actual Move-Out',
        ],
        'confirm' => [
            'delete_deduction' => 'Are you sure you want to remove this deduction?',
            'cancel_moveout' => 'Are you sure you want to cancel this move-out? The tenant will remain in the unit.',
        ],
        'deduction_modal' => [
            'edit_title' => 'Edit Deduction',
            'add_title' => 'Add Deduction',
            'category_label' => 'Category (Optional)',
            'custom_option' => 'Custom Deduction',
            'description_label' => 'Description *',
            'description_placeholder' => 'e.g., Wall damage repair',
            'amount_label' => 'Amount ({currency}) *',
            'notes_label' => 'Notes (Optional)',
            'cancel' => 'Cancel',
            'saving' => 'Saving...',
            'update' => 'Update',
            'add_button' => 'Add Deduction',
        ],
        'settlement_modal' => [
            'title' => 'Complete Settlement',
            'refund_to_tenant' => 'Refund to Tenant',
            'method_label' => 'Settlement Method *',
            'method_cash' => 'Cash',
            'method_bank_transfer' => 'Bank Transfer',
            'method_mobile_money' => 'Mobile Money (M-Pesa)',
            'method_offset' => 'Offset Against Arrears',
            'reference_label' => 'Reference Number (Optional)',
            'reference_placeholder' => 'Transaction ID or receipt number',
            'warning' => 'This action will end the lease and mark the unit as vacant.',
            'cancel' => 'Cancel',
            'processing' => 'Processing...',
            'complete' => 'Complete Move-Out',
        ],
    ],
];
