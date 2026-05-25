<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone refund-creation page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'معالجة استرداد',
    'back_to_refunds' => 'العودة إلى المستردات',
    'heading' => 'معالجة استرداد',
    'subheading' => 'إنشاء طلب استرداد لدفعة مستأجر',
    'success' => [
        'title' => 'تم إنشاء طلب الاسترداد!',
        'body' => 'تم تقديم الاسترداد للمعالجة.',
        'view_refunds' => 'عرض المستردات',
    ],
    'tenant_selection' => 'اختيار المستأجر',
    'change' => 'تغيير',
    'search_placeholder' => 'ابحث عن مستأجر بالاسم أو الهاتف أو رقم الوحدة...',
    'no_unit' => 'لا توجد وحدة',
    'payment_selection' => 'اختيار الدفعة',
    'loading_payments' => 'جارٍ تحميل المدفوعات...',
    'no_refundable_payments' => 'لا توجد مدفوعات قابلة للاسترداد لهذا المستأجر',
    'invoice_prefix' => 'الفاتورة:',
    'of_amount' => 'من {amount}',
    'refund_details' => 'تفاصيل الاسترداد',
    'amount_label' => 'المبلغ *',
    'amount_placeholder' => '0.00',
    'max' => 'الحد الأقصى',
    'max_refundable' => 'الحد الأقصى القابل للاسترداد: {amount}',
    'refund_method_label' => 'طريقة الاسترداد *',
    'reason_label' => 'السبب *',
    'select_reason' => 'اختر سببًا...',
    'specify_reason_label' => 'حدد السبب *',
    'custom_reason_placeholder' => 'أدخل سبب هذا الاسترداد...',
    'notes_label' => 'ملاحظات (اختياري)',
    'notes_placeholder' => 'أي ملاحظات إضافية...',
    'original_payment' => 'الدفعة الأصلية',
    'already_refunded' => 'تم استرداده بالفعل',
    'this_refund' => 'هذا الاسترداد',
    'cancel' => 'إلغاء',
    'processing' => 'جارٍ المعالجة...',
    'create_refund' => 'إنشاء استرداد',
    'payment_methods' => [
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'mobile_money' => 'M-Pesa',
        'paystack' => 'Paystack (عبر الإنترنت)',
    ],
    'errors' => [
        'select_tenant' => 'يرجى اختيار مستأجر',
        'select_payment' => 'يرجى اختيار دفعة للاسترداد',
        'valid_amount' => 'يرجى إدخال مبلغ صالح',
        'amount_exceeds' => 'لا يمكن أن يتجاوز المبلغ {amount}',
        'select_reason' => 'يرجى اختيار سبب',
        'specify_reason' => 'يرجى تحديد السبب',
        'select_method' => 'يرجى اختيار طريقة الاسترداد',
    ],
];
