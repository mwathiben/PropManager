<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub refunds tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'البحث في المستردات...',
    'actions' => [
        'process_refund' => 'معالجة الاسترداد',
        'view' => 'عرض',
    ],
    'columns' => [
        'payment_ref' => 'مرجع الدفع',
        'tenant' => 'المستأجر',
        'amount' => 'المبلغ',
        'reason' => 'السبب',
        'status' => 'الحالة',
        'requested' => 'تاريخ الطلب',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'غير معروف',
        'no_unit' => 'غير متوفر',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'مُعتمد',
        'processing' => 'قيد المعالجة',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'cancelled' => 'مُلغى',
    ],
    'empty' => [
        'title' => 'لا توجد مستردات',
        'description' => 'ستظهر طلبات الاسترداد هنا',
    ],
];
