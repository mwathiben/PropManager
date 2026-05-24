<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: late-fee settings tab (Finances). Mirror en/sw/ar.
 */
return [
    'apply_now' => 'Apply late fees now',
    'stats' => [
        'active_policies' => 'Active Policies',
        'fees_this_month' => 'Fees This Month',
        'total_applied' => 'Total Applied',
        'total_waived' => 'Total Waived',
    ],
    'policies' => [
        'title' => 'Late Fee Policies',
        'subtitle' => 'Configure automatic late fee rules for overdue invoices',
        'add' => 'Add Policy',
    ],
    'form' => [
        'name' => 'Policy Name *',
        'name_placeholder' => 'e.g., Default Late Fee',
        'property' => 'Property (Optional)',
        'property_all' => 'All Properties (Default)',
        'building' => 'Building (Optional)',
        'building_all' => 'All Buildings',
        'grace_period' => 'Grace Period (days) *',
        'grace_period_hint' => 'Days after due date before fee applies',
        'fee_type' => 'Fee Type *',
        'fee_type_percentage' => 'Percentage (%)',
        'fee_type_fixed' => 'Flat Amount ({currency})',
        'fee_percentage' => 'Fee Percentage *',
        'fee_amount' => 'Fee Amount *',
        'max_fee_cap' => 'Max Fee Cap (Optional)',
        'max_fee_cap_placeholder' => 'No limit',
        'compounding' => 'Compounding (apply fee multiple times)',
        'frequency' => 'Frequency:',
        'frequency_daily' => 'Daily',
        'frequency_weekly' => 'Weekly',
        'frequency_monthly' => 'Monthly',
        'active' => 'Active',
        'cancel' => 'Cancel',
        'saving' => 'Saving...',
        'update' => 'Update Policy',
        'create' => 'Create Policy',
    ],
    'empty' => [
        'title' => 'No late fee policies',
        'subtitle' => 'Get started by creating a late fee policy.',
        'add_first' => 'Add Your First Policy',
    ],
    'list' => [
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'grace_period' => '{days} day grace period',
        'compounds' => '| Compounds {frequency}',
        'max' => '| Max {amount}',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],
    'delete' => [
        'title' => 'Delete Policy',
        'confirm' => 'Are you sure you want to delete "{name}"? This action cannot be undone.',
        'cancel' => 'Cancel',
        'confirm_btn' => 'Delete',
    ],
];
