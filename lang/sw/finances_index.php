<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: Finance Hub landing page (Pages/Finances/Index.vue).
 * Hub-shell chrome + tab names map + page title + breadcrumb fallback + designOptions defaults.
 * Mirror en/sw/ar.
 */
return [
    'heading' => 'Kitovu cha Fedha',
    'subheading' => 'Simamia ankara, malipo, na shughuli za kifedha',
    'page_title' => 'Kitovu cha Fedha - {tab}',
    'breadcrumb_root' => 'Kitovu cha Fedha',
    'unknown_tenant' => 'Haijulikani',
    'tabs' => [
        'overview' => 'Muhtasari',
        'invoices' => 'Ankara',
        'payments' => 'Malipo',
        'expenses' => 'Matumizi',
        'refunds' => 'Marejesho',
        'reconciliation' => 'Upatanisho',
        'deposits' => 'Amana',
        'arrears' => 'Madeni',
        'late_fees' => 'Ada za Kuchelewa',
        'reports' => 'Ripoti',
        'template_invoices' => 'Violezo vya Ankara',
        'template_receipts' => 'Violezo vya Risiti',
        'template_credit_notes' => 'Violezo vya Hati za Mikopo',
        'settings' => 'Mipangilio',
    ],
    'design_options' => [
        'classic' => 'Asili',
        'modern' => 'Kisasa',
        'minimal' => 'Sahili',
        'professional' => 'Kitaalamu',
    ],
];
