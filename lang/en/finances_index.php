<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: Finance Hub landing page (Pages/Finances/Index.vue).
 * Hub-shell chrome + tab names map + page title + breadcrumb fallback + designOptions defaults.
 * Mirror en/sw/ar.
 */
return [
    'heading' => 'Finance Hub',
    'subheading' => 'Manage invoices, payments, and financial operations',
    'page_title' => 'Finance Hub - {tab}',
    'breadcrumb_root' => 'Finance Hub',
    'unknown_tenant' => 'Unknown',
    'tabs' => [
        'overview' => 'Overview',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'expenses' => 'Expenses',
        'refunds' => 'Refunds',
        'reconciliation' => 'Reconciliation',
        'deposits' => 'Deposits',
        'arrears' => 'Arrears',
        'late_fees' => 'Late Fees',
        'reports' => 'Reports',
        'template_invoices' => 'Invoice Templates',
        'template_receipts' => 'Receipt Templates',
        'template_credit_notes' => 'Credit Note Templates',
        'settings' => 'Settings',
    ],
    'design_options' => [
        'classic' => 'Classic',
        'modern' => 'Modern',
        'minimal' => 'Minimal',
        'professional' => 'Professional',
    ],
];
