<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant home dashboard page. Mirror en/sw/ar.
 */
return [
    'title' => 'لوحة التحكم الخاصة بي',
    'welcome' => 'مرحبًا بك في {building}',
    'unit_floor' => 'الوحدة {unit} • الطابق {floor}',
    'pay_now' => 'ادفع الآن',
    'no_lease' => [
        'pending_title' => 'لديك دعوات معلقة',
        'pending_subtitle' => 'دعاك أحد الملاك لاستئجار عقار. راجع التفاصيل أدناه واقبل للبدء.',
        'no_active_lease' => 'لا يوجد عقد إيجار نشط',
    ],
    'invitation' => [
        'monthly_rent' => 'الإيجار الشهري',
        'security_deposit' => 'مبلغ التأمين',
        'start_date' => 'تاريخ البدء',
        'floor' => 'الطابق',
        'service_charge' => 'رسوم الخدمة',
        'per_month' => '/شهر',
        'total_move_in' => 'إجمالي تكلفة الانتقال',
        'landlord' => 'المالك',
        'expires' => 'تنتهي {date}',
        'processing' => 'جارٍ المعالجة...',
        'accept' => 'قبول الدعوة',
        'unit_label' => '{building} • الوحدة {unit}',
    ],
    'confirm' => [
        'decline' => 'هل أنت متأكد من رفض هذه الدعوة؟ لا يمكن التراجع عن هذا الإجراء.',
    ],
    'balance' => [
        'current' => 'الرصيد الحالي',
        'credit' => 'رصيد دائن',
        'arrears' => 'متأخرات مستحقة',
    ],
    'action_items' => [
        'overdue_invoices' => 'فواتير متأخرة',
        'days_late' => 'متأخر بـ {days} أيام',
        'pending_invoices' => 'فواتير معلقة',
        'awaiting_payment' => 'في انتظار الدفع',
        'view' => 'عرض',
        'all_paid' => 'تم دفع الكل',
        'no_pending_invoices' => 'لا توجد فواتير معلقة',
        'open_tickets' => 'تذاكر مفتوحة',
        'issues_being_resolved' => 'تتم معالجة المشكلات',
        'no_issues' => 'لا توجد مشكلات',
        'all_tickets_resolved' => 'تم حل جميع التذاكر',
        'monthly_rent' => 'الإيجار الشهري',
        'due_monthly' => 'مستحق شهريًا',
    ],
    'next_payment' => [
        'title' => 'الدفعة التالية المستحقة',
        'pay_invoice' => 'دفع الفاتورة',
        'view_details' => 'عرض التفاصيل',
    ],
    'tickets' => [
        'title' => 'تذاكري',
        'view_all' => 'عرض الكل',
        'none' => 'لا توجد تذاكر نشطة',
        'all_good' => 'كل شيء يبدو على ما يرام!',
        'report_issue' => 'الإبلاغ عن مشكلة',
    ],
    'payments' => [
        'title' => 'سجل المدفوعات',
        'view_all' => 'عرض الكل',
        'none' => 'لا يوجد سجل مدفوعات',
        'fallback_method' => 'دفعة',
    ],
    'lease' => [
        'title' => 'معلومات عقد الإيجار',
        'view_details' => 'عرض التفاصيل',
        'start_date' => 'تاريخ البدء',
        'end_date' => 'تاريخ الانتهاء',
        'open_ended' => 'مفتوح المدة',
        'monthly_rent' => 'الإيجار الشهري',
        'deposit_paid' => 'التأمين المدفوع',
    ],
    'caretaker' => [
        'title' => 'حارس المبنى',
        'whatsapp' => 'واتساب',
        'none' => 'لم يتم تعيين حارس',
    ],
];
