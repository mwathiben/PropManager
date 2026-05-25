<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: operations hub imports tab. Mirror en/sw/ar.
 */
return [
    'import_data' => 'Import Data',
    'import_history' => 'Import History',
    'no_imports' => 'No imports yet',
    'no_imports_hint' => 'Import history will appear here.',
    'template_button' => 'Template',
    'import_button' => 'Import',
    'import_modal_title' => 'Import {type}',
    'csv_file_label' => 'CSV File',
    'csv_file_hint' => 'Download the template first to see the required format.',
    'cancel' => 'Cancel',
    'start_import' => 'Start Import',
    'table' => [
        'type' => 'Type',
        'file' => 'File',
        'records' => 'Records',
        'status' => 'Status',
        'date' => 'Date',
    ],
    'templates' => [
        'tenants' => [
            'name' => 'Tenants',
            'description' => 'Import tenant information from CSV',
        ],
        'units' => [
            'name' => 'Units',
            'description' => 'Import unit data from CSV',
        ],
        'payments' => [
            'name' => 'Payments',
            'description' => 'Import payment records from CSV',
        ],
    ],
];
