<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone invoice detail page
 * (resources/js/Pages/Invoices/Show.vue). Mirror en/sw/ar.
 */
return [
    'head_title' => 'فاتورة {number}',
    'page_title' => 'فاتورة {number}',
    'tenant_unit' => '{tenant} - وحدة {unit}',
    'legal_hold' => 'الحجز القانوني',
    'hold_history' => 'سجل الحجز',
    'total_due' => 'الإجمالي المستحق',
    'amount_paid' => 'المبلغ المدفوع',
    'remaining_balance' => 'الرصيد المتبقي',
    'due_date' => 'تاريخ الاستحقاق',
    'billing_period' => 'فترة الفوترة',
    'billing_period_range' => '{start} - {end}',
    'payment_progress' => 'تقدّم السداد',
    'paid_amount' => 'المدفوع: {amount}',
    'total_amount' => 'الإجمالي: {amount}',
    'line_items' => 'بنود الفاتورة',
    'rent' => 'الإيجار',
    'water_charges' => 'رسوم المياه',
    'previous_arrears' => 'المتأخرات السابقة',
    'payment_history' => 'سجل المدفوعات',
    'payment_meta' => '{method} - {date}',
    'reference' => 'مرجع: {reference}',
    'generating_pdf' => 'جارٍ إنشاء PDF...',
    'status' => [
        'draft' => 'مسودة',
        'sent' => 'مُرسلة',
        'partial' => 'جزئية',
        'paid' => 'مدفوعة',
        'overdue' => 'متأخرة',
        'voided' => 'ملغاة',
    ],
    'payment_methods' => [
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'mobile_money' => 'الدفع عبر الموبايل',
    ],
    'actions' => [
        'preview_pdf' => 'معاينة PDF',
        'download_pdf' => 'تنزيل PDF',
        'downloading' => 'جارٍ الإنشاء...',
        'mark_sent' => 'تعليم كمُرسلة',
        'send_reminder' => 'إرسال تذكير',
        'record_payment' => 'تسجيل دفعة',
        'void_invoice' => 'إلغاء الفاتورة',
        'reissue_invoice' => 'إعادة إصدار الفاتورة',
    ],
    'payment_modal' => [
        'title' => 'تسجيل دفعة',
        'amount' => 'المبلغ',
        'payment_method' => 'طريقة الدفع',
        'reference_optional' => 'المرجع (اختياري)',
        'cancel' => 'إلغاء',
        'submit' => 'تسجيل دفعة',
    ],
    'void_modal' => [
        'title' => 'إلغاء الفاتورة',
        'warning' => 'هل أنت متأكد من إلغاء هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.',
        'reason_label' => 'سبب الإلغاء',
        'reason_placeholder' => 'أدخل السبب...',
        'cancel' => 'إلغاء',
        'submit' => 'إلغاء الفاتورة',
    ],
];
