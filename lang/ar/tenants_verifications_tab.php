<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page verifications tab. Mirror en/sw/ar.
 */
return [
    'filters' => [
        'search_placeholder' => 'البحث في عمليات التحقق...',
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
        'submitted' => 'تم الإرسال',
        'status' => 'الحالة',
    ],
    'empty' => [
        'title' => 'لا توجد عمليات تحقق',
        'description_filtered' => 'حاول تعديل عوامل التصفية.',
        'description_default' => 'ستظهر عمليات التحقق من المستأجرين هنا.',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من {total} نتيجة',
    ],
    'unknown' => 'غير معروف',
];
