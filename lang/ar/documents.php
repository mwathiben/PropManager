<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: documents list/management page. Mirror en/sw/ar.
 */
return [
    'title' => 'المستندات',
    'heading' => [
        'mine' => 'مستنداتي',
        'all' => 'المستندات',
    ],
    'subtitle' => [
        'mine' => 'عرض مستندات وملفات عقد الإيجار الخاص بك',
        'all' => 'إدارة اتفاقيات الإيجار ومستندات المستأجرين والملفات',
    ],
    'upload' => 'رفع مستند',
    'filters' => [
        'search' => 'بحث',
        'search_placeholder' => 'ابحث عن المستندات...',
        'document_type' => 'نوع المستند',
        'attached_to' => 'مرفق بـ',
        'building_wing' => 'المبنى / الجناح',
        'apply' => 'تطبيق',
        'clear' => 'مسح',
    ],
    'type' => [
        'all' => 'جميع الأنواع',
        'lease_agreement' => 'اتفاقية الإيجار',
        'tenant_id' => 'هوية المستأجر',
        'tenant_passport' => 'جواز السفر',
        'bank_statement' => 'كشف حساب بنكي',
        'payslip' => 'قسيمة الراتب',
        'reference_letter' => 'خطاب مرجعي',
        'utility_bill' => 'فاتورة الخدمات',
        'other' => 'أخرى',
    ],
    'attached' => [
        'all' => 'الكل',
        'leases' => 'عقود الإيجار',
        'tenants' => 'المستأجرون',
    ],
    'table' => [
        'document' => 'المستند',
        'type' => 'النوع',
        'attached_to' => 'مرفق بـ',
        'size' => 'الحجم',
        'uploaded' => 'تم الرفع',
        'actions' => 'الإجراءات',
    ],
    'select_row' => 'اختر {title}',
    'uploaded_by' => 'بواسطة {name}',
    'actions' => [
        'view' => 'عرض',
        'download' => 'تنزيل',
        'delete' => 'حذف',
    ],
    'confirm' => [
        'delete' => 'هل أنت متأكد أنك تريد حذف هذا المستند؟ لا يمكن التراجع عن هذا الإجراء.',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من إجمالي {total} مستند',
    ],
    'empty' => [
        'title' => 'لم يتم العثور على مستندات',
        'description' => [
            'mine' => 'لم تتم مشاركة أي مستندات معك بعد.',
            'all' => 'ارفع مستندك الأول للبدء.',
        ],
        'action' => 'رفع أول مستند',
    ],
];
