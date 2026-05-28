<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: settings payout accounts page (Settings/PayoutAccounts).
 * Tenant/landlord payout destinations (M-Pesa, bank, etc.). Mirror en/sw/ar.
 */
return [
    'title' => 'Payout Accounts',
    'header' => 'Payout Accounts',
    'add_account' => 'Add Account',
    'fee_banner' => [
        'heading' => 'Platform Fee Information',
        'billing_model_label' => 'Current billing model:',
        'fee_label' => 'Platform fee:',
        'fee_per_transaction_suffix' => 'per transaction',
        'description' => 'Connect your bank account to receive payments directly. The platform fee will be automatically deducted.',
    ],
    'billing_models' => [
        'transaction_fee' => 'Transaction Fee',
        'subscription' => 'Subscription',
        'hybrid' => 'Hybrid',
    ],
    'alert' => [
        'heading' => 'Payout Account Required',
        'description' => 'You need to connect a verified payout account before tenants can make online payments.',
    ],
    'badge' => [
        'primary' => 'Primary',
    ],
    'actions' => [
        'set_primary' => 'Set as primary payout account',
        'sync_status' => 'Sync account status',
        'deactivate' => 'Deactivate payout account',
    ],
    'empty' => [
        'title' => 'No payout accounts',
        'description' => 'Get started by connecting your bank account.',
        'action' => 'Add Account',
    ],
    'modal' => [
        'title' => 'Add Payout Account',
        'business_name' => 'Business Name',
        'business_name_placeholder' => 'Your business or property name',
        'bank' => 'Bank',
        'select_bank' => 'Select a bank',
        'loading_banks' => 'Loading banks...',
        'account_number' => 'Account Number',
        'account_number_placeholder' => 'Enter account number',
        'verify' => 'Verify',
        'verifying' => 'Verifying...',
        'verified_heading' => 'Account Verified',
        'cancel' => 'Cancel',
        'adding' => 'Adding...',
        'submit' => 'Add Account',
    ],
    'confirm' => [
        'deactivate' => 'Are you sure you want to deactivate this payout account?',
    ],
    'errors' => [
        'verify_failed' => 'Could not verify account',
        'verify_exception' => 'Account verification failed',
    ],
];
