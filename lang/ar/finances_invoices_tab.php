<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub invoices tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'البحث في الفواتير...',
    'actions' => [
        'generate_invoices' => 'إنشاء الفواتير',
        'view' => 'عرض',
        'record_payment' => 'تسجيل دفعة',
        'cancel' => 'إلغاء',
    ],
    'columns' => [
        'invoice_number' => 'رقم الفاتورة',
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'amount' => 'المبلغ',
        'paid' => 'المدفوع',
        'status' => 'الحالة',
        'due_date' => 'تاريخ الاستحقاق',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'غير معروف',
        'no_unit' => 'غير متوفر',
    ],
    'empty' => [
        'title' => 'لا توجد فواتير',
        'description' => 'أنشئ فواتير للبدء',
    ],
    'generate_modal' => [
        'title' => 'إنشاء الفواتير',
        'description' => 'أنشئ فواتير لجميع عقود الإيجار النشطة لفترة الفوترة المحددة.',
        'month_label' => 'الشهر',
        'year_label' => 'السنة',
    ],
    'months' => [
        'january' => 'يناير',
        'february' => 'فبراير',
        'march' => 'مارس',
        'april' => 'أبريل',
        'may' => 'مايو',
        'june' => 'يونيو',
        'july' => 'يوليو',
        'august' => 'أغسطس',
        'september' => 'سبتمبر',
        'october' => 'أكتوبر',
        'november' => 'نوفمبر',
        'december' => 'ديسمبر',
    ],
];
