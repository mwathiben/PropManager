<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone PaymentVerifications/Index page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'التحقق من المدفوعات',
    'header' => [
        'title' => 'التحقق من المدفوعات',
        'subtitle' => 'راجع واعتمد مدفوعات المستأجرين الجديدة',
        'awaiting_review_badge' => '{count} في انتظار المراجعة',
    ],
    'filters' => [
        'search_placeholder' => 'البحث باسم المستأجر...',
    ],
    'status_options' => [
        'all' => 'جميع الحالات',
        'awaiting_review' => 'في انتظار المراجعة',
        'pending_payment' => 'في انتظار الدفع',
        'verified' => 'تم التحقق',
        'rejected' => 'مرفوض',
    ],
    'table' => [
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'total_required' => 'الإجمالي المطلوب',
        'status' => 'الحالة',
        'submitted' => 'تم الإرسال',
        'documents' => 'المستندات',
        'actions' => 'الإجراءات',
    ],
    'unknown_tenant' => 'غير معروف',
    'actions' => [
        'view' => 'عرض',
    ],
    'empty' => 'لم يتم العثور على أي تحقق من المدفوعات',
];
