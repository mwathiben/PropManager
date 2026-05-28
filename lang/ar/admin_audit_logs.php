<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin (super-admin) audit-log viewer page.
 * Distinct from the landlord-facing `activity_logs` namespace.
 * Mirror en/sw/ar.
 */
return [
    'title' => 'سجلات التدقيق',
    'export_csv' => 'تصدير CSV',
    'filters' => [
        'search' => 'بحث',
        'search_placeholder' => 'بحث...',
        'event_type' => 'نوع الحدث',
        'all_events' => 'جميع الأحداث',
        'model_type' => 'نوع النموذج',
        'all_models' => 'جميع النماذج',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'clear' => 'مسح المرشحات',
        'apply' => 'تطبيق المرشحات',
    ],
    'columns' => [
        'datetime' => 'التاريخ/الوقت',
        'user' => 'المستخدم',
        'event' => 'الحدث',
        'model' => 'النموذج',
        'changes' => 'التغييرات',
        'ip' => 'IP',
        'actions' => 'الإجراءات',
    ],
    'system_user' => 'النظام',
    'view_details' => 'عرض التفاصيل',
    'empty' => [
        'title' => 'لم يتم العثور على سجلات تدقيق',
        'body' => 'اضبط المرشحات أعلاه. يتم إنشاء سجلات التدقيق تلقائيًا عندما يتصرف المستخدمون على السجلات.',
    ],
];
