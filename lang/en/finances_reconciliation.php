<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: bank reconciliation tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Bank Reconciliation',
    'subtitle' => 'Import bank statements and match transactions to invoices',
    'auto_match_all' => 'Auto-Match All',
    'import_statement' => 'Import Statement',
    'paystack' => [
        'heading' => 'Paystack Reconciliation',
        'last_run_failed' => 'Last run failed: {message}',
        'status' => [
            'failed' => 'Failed',
            'discrepancies' => '{count} discrepancies',
            'clean' => 'Clean',
        ],
        'matched' => 'Matched',
        'local' => 'Local',
        'remote' => 'Remote',
        'discrepancies' => 'Discrepancies',
    ],
    'import' => [
        'heading' => 'Import Bank Statement',
        'bank_label' => 'Bank',
        'bank_placeholder' => 'Select bank...',
        'file_label' => 'CSV/Excel File',
        'file_hint' => 'Max 5MB. Supported: CSV, XLSX, XLS',
        'column_mapping_toggle' => 'Column Mapping (Optional)',
        'column_mapping_hint' => 'Specify column names if they differ from defaults (reference, amount, date, description)',
        'reference_column' => 'Reference Column',
        'amount_column' => 'Amount Column',
        'date_column' => 'Date Column',
        'description_column' => 'Description Column',
        'cancel' => 'Cancel',
        'importing' => 'Importing...',
        'submit' => 'Import',
    ],
    'banks' => [
        'equity' => 'Equity Bank',
        'kcb' => 'KCB Bank',
        'coop' => 'Co-operative Bank',
        'stanbic' => 'Stanbic Bank',
        'absa' => 'Absa Bank',
        'ncba' => 'NCBA Bank',
        'dtb' => 'DTB Bank',
        'i_and_m' => 'I&M Bank',
        'family' => 'Family Bank',
        'other' => 'Other Bank',
    ],
    'stats' => [
        'pending' => 'Pending',
        'unmatched' => 'Unmatched',
        'matched' => 'Matched',
        'unmatched_amount' => 'Unmatched Amount',
    ],
    'pending' => [
        'heading' => 'Pending Reconciliation',
        'body' => 'You have {count} payment(s) that need to be matched to invoices.',
    ],
    'reconciled' => [
        'heading' => 'All Reconciled',
        'body' => 'All payments have been matched to invoices. Import a bank statement to reconcile new transactions.',
    ],
    'table' => [
        'reference' => 'Reference',
        'tenant' => 'Tenant',
        'amount' => 'Amount',
        'method' => 'Method',
        'date' => 'Date',
        'empty_title' => 'No unmatched payments',
        'empty_description' => 'Import a bank statement to start reconciling transactions',
        'match' => 'Match',
    ],
    'placeholders' => [
        'reference' => 'reference',
        'amount' => 'amount',
        'date' => 'date',
        'description' => 'description',
    ],
    'fallback' => [
        'unknown_tenant' => 'Unknown',
        'no_unit' => 'N/A',
    ],
];
