<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: operations hub imports tab. Mirror en/sw/ar.
 */
return [
    'import_data' => 'استيراد البيانات',
    'import_history' => 'سجل الاستيراد',
    'no_imports' => 'لا توجد عمليات استيراد بعد',
    'no_imports_hint' => 'سيظهر سجل الاستيراد هنا.',
    'template_button' => 'القالب',
    'import_button' => 'استيراد',
    'import_modal_title' => 'استيراد {type}',
    'csv_file_label' => 'ملف CSV',
    'csv_file_hint' => 'قم بتنزيل القالب أولاً لمعرفة التنسيق المطلوب.',
    'cancel' => 'إلغاء',
    'start_import' => 'بدء الاستيراد',
    'table' => [
        'type' => 'النوع',
        'file' => 'الملف',
        'records' => 'السجلات',
        'status' => 'الحالة',
        'date' => 'التاريخ',
    ],
    'templates' => [
        'tenants' => [
            'name' => 'المستأجرون',
            'description' => 'استيراد معلومات المستأجرين من CSV',
        ],
        'units' => [
            'name' => 'الوحدات',
            'description' => 'استيراد بيانات الوحدات من CSV',
        ],
        'payments' => [
            'name' => 'المدفوعات',
            'description' => 'استيراد سجلات المدفوعات من CSV',
        ],
    ],
];
