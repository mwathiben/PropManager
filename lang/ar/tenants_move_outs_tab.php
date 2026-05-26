<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page move-outs tab. Mirror en/sw/ar.
 */
return [
    'stats' => [
        'active' => 'نشط',
        'inspection_pending' => 'التفتيش معلق',
        'settlement_pending' => 'التسوية معلقة',
        'completed_this_month' => 'مكتمل (الشهر)',
    ],
    'filter' => [
        'active' => 'نشط',
        'completed' => 'مكتمل',
    ],
    'table' => [
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'initiated' => 'تم البدء',
        'status' => 'الحالة',
        'actions' => 'الإجراءات',
    ],
    'status' => [
        'notice_given' => 'تم إعطاء الإشعار',
        'inspection_pending' => 'التفتيش معلق',
        'inspection_complete' => 'التفتيش مكتمل',
        'settlement_pending' => 'التسوية معلقة',
        'completed' => 'مكتمل',
        'settled' => 'تمت التسوية',
        'cancelled' => 'ملغى',
    ],
    'actions' => [
        'view' => 'عرض',
    ],
    'empty' => [
        'title' => 'لا توجد عمليات إخلاء',
        'description' => 'ستظهر حالات الإخلاء هنا.',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من {total} نتيجة',
    ],
    'unknown' => 'غير معروف',
    'unit_prefix' => 'الوحدة {number}',
];
