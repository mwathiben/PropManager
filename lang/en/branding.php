<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: white-label branding settings tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Branding',
    'intro' => 'Customize how your invoices and receipts look to tenants.',
    'logo' => [
        'heading' => 'Business Logo',
        'description' => 'Your logo will appear on invoices and receipts. Recommended size: 200x80 pixels.',
        'alt' => 'Business logo',
        'delete_title' => 'Delete logo',
        'click_to_upload' => 'Click to upload',
        'uploading' => 'Uploading...',
        'change' => 'Change Logo',
        'upload' => 'Upload Logo',
        'accepted_formats' => 'Accepted formats: JPEG, PNG, GIF, SVG. Max size: 2MB.',
        'size_error' => 'Logo file must be less than 2MB',
        'delete_confirm' => 'Are you sure you want to delete your business logo?',
    ],
    'numbering' => [
        'heading' => 'Invoice Numbering',
        'format_label' => 'Invoice Number Format',
        'example' => '{format} (e.g., {example})',
        'legend' => '{yyyy} = Year, {mm} = Month, {nnnn} = Sequential number',
    ],
    'footers' => [
        'heading' => 'Document Footers',
        'invoice_label' => 'Invoice Footer Text',
        'invoice_placeholder' => 'e.g., Thank you for your business. Payment is due within 7 days.',
        'invoice_help' => 'This text appears at the bottom of all invoices (max 500 characters)',
        'receipt_label' => 'Receipt Footer Text',
        'receipt_placeholder' => 'e.g., Thank you for your payment. This receipt is auto-generated.',
        'receipt_help' => 'This text appears at the bottom of all payment receipts (max 500 characters)',
    ],
    'preview' => [
        'heading' => 'Invoice Preview',
        'logo_alt' => 'Logo',
        'no_logo' => 'No Logo',
        'company_name' => 'Your Company Name',
        'invoice' => 'INVOICE',
        'footer_placeholder' => 'Your invoice footer text will appear here',
    ],
    'save' => [
        'saving' => 'Saving...',
        'submit' => 'Save Branding Settings',
    ],
];
