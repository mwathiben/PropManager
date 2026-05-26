<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page payment-verifications tab. Mirror en/sw/ar.
 */
return [
    'filters' => [
        'search_placeholder' => 'البحث في عمليات التحقق من الدفع...',
        'all_status' => 'كل الحالات',
        'clear' => 'مسح عوامل التصفية',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
    ],
    'table' => [
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'amount' => 'المبلغ',
        'status' => 'الحالة',
        'actions' => 'إجراءات',
    ],
    'actions' => [
        'view' => 'عرض',
    ],
    'empty' => [
        'title' => 'لا توجد عمليات تحقق من الدفع',
        'description_filtered' => 'حاول تعديل عوامل التصفية.',
        'description_default' => 'ستظهر عمليات التحقق من الدفع هنا.',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من {total} نتيجة',
    ],
    'unknown' => 'غير معروف',
    'unit_prefix' => 'وحدة {number}',
];
