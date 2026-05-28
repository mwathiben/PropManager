<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: Finance Hub landing page (Pages/Finances/Index.vue).
 * Hub-shell chrome + tab names map + page title + breadcrumb fallback + designOptions defaults.
 * Mirror en/sw/ar.
 */
return [
    'heading' => 'مركز المالية',
    'subheading' => 'إدارة الفواتير والمدفوعات والعمليات المالية',
    'page_title' => 'مركز المالية - {tab}',
    'breadcrumb_root' => 'مركز المالية',
    'unknown_tenant' => 'غير معروف',
    'tabs' => [
        'overview' => 'نظرة عامة',
        'invoices' => 'الفواتير',
        'payments' => 'المدفوعات',
        'expenses' => 'المصروفات',
        'refunds' => 'المبالغ المستردة',
        'reconciliation' => 'التسوية',
        'deposits' => 'الودائع',
        'arrears' => 'المتأخرات',
        'late_fees' => 'رسوم التأخير',
        'reports' => 'التقارير',
        'template_invoices' => 'قوالب الفواتير',
        'template_receipts' => 'قوالب الإيصالات',
        'template_credit_notes' => 'قوالب إشعارات الدائن',
        'settings' => 'الإعدادات',
    ],
    'design_options' => [
        'classic' => 'كلاسيكي',
        'modern' => 'حديث',
        'minimal' => 'بسيط',
        'professional' => 'احترافي',
    ],
];
