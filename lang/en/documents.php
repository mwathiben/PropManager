<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: documents list/management page. Mirror en/sw/ar.
 */
return [
    'title' => 'Documents',
    'heading' => [
        'mine' => 'My Documents',
        'all' => 'Documents',
    ],
    'subtitle' => [
        'mine' => 'View your lease documents and files',
        'all' => 'Manage lease agreements, tenant documents, and files',
    ],
    'upload' => 'Upload Document',
    'filters' => [
        'search' => 'Search',
        'search_placeholder' => 'Search documents...',
        'document_type' => 'Document Type',
        'attached_to' => 'Attached To',
        'building_wing' => 'Building / Wing',
        'apply' => 'Apply',
        'clear' => 'Clear',
    ],
    'type' => [
        'all' => 'All Types',
        'lease_agreement' => 'Lease Agreement',
        'tenant_id' => 'Tenant ID',
        'tenant_passport' => 'Passport',
        'bank_statement' => 'Bank Statement',
        'payslip' => 'Payslip',
        'reference_letter' => 'Reference Letter',
        'utility_bill' => 'Utility Bill',
        'other' => 'Other',
    ],
    'attached' => [
        'all' => 'All',
        'leases' => 'Leases',
        'tenants' => 'Tenants',
    ],
    'table' => [
        'document' => 'Document',
        'type' => 'Type',
        'attached_to' => 'Attached To',
        'size' => 'Size',
        'uploaded' => 'Uploaded',
        'actions' => 'Actions',
    ],
    'select_row' => 'Select {title}',
    'uploaded_by' => 'by {name}',
    'actions' => [
        'view' => 'View',
        'download' => 'Download',
        'delete' => 'Delete',
    ],
    'confirm' => [
        'delete' => 'Are you sure you want to delete this document? This action cannot be undone.',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} documents',
    ],
    'empty' => [
        'title' => 'No documents found',
        'description' => [
            'mine' => 'No documents have been shared with you yet.',
            'all' => 'Upload your first document to get started.',
        ],
        'action' => 'Upload First Document',
    ],
];
