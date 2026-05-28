<?php

declare(strict_types=1);

return [
    'page_title' => 'إشعارات الدائن',
    'header_title' => 'إشعارات الدائن',
    'header_subtitle' => 'إصدار وإدارة أرصدة حسابات المستأجرين',
    'breadcrumb' => [
        'finance_hub' => 'مركز المالية',
        'credit_notes' => 'إشعارات الدائن',
    ],
    'stats' => [
        'total' => 'الإجمالي',
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'applied' => 'مُطبَّق',
        'total_amount' => 'المبلغ الإجمالي',
    ],
    'filters' => [
        'search_placeholder' => 'البحث في إشعارات الدائن...',
        'all_statuses' => 'جميع الحالات',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'موافق عليه',
        'applied' => 'مُطبَّق',
        'voided' => 'ملغي',
    ],
    'actions' => [
        'issue' => 'إصدار إشعار دائن',
        'view' => 'عرض',
    ],
    'table' => [
        'credit_number' => 'رقم الدائن',
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'amount' => 'المبلغ',
        'reason' => 'السبب',
        'status' => 'الحالة',
        'date' => 'التاريخ',
        'actions' => 'الإجراءات',
        'applied_amount' => 'مُطبَّق: {amount}',
    ],
    'empty' => [
        'title' => 'لا توجد إشعارات دائن',
        'subtitle' => 'أصدر إشعار دائن لتعديل أرصدة المستأجرين',
    ],
];
