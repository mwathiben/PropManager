<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: manual payment-recording form. Mirror en/sw/ar.
 */
return [
    'page_title' => 'تسجيل دفعة',
    'back' => 'العودة إلى المدفوعات',
    'heading' => 'تسجيل دفعة',
    'subheading' => 'تسجيل دفعة من مستأجر يدويًا',
    'success' => [
        'title' => 'تم تسجيل الدفعة!',
        'body' => 'تم تسجيل الدفعة بنجاح.',
        'view_payments' => 'عرض المدفوعات',
    ],
    'tenant' => [
        'section' => 'اختيار المستأجر',
        'change' => 'تغيير',
        'search_placeholder' => 'ابحث عن مستأجر بالاسم أو الهاتف أو رقم الوحدة...',
        'no_unit' => 'لا توجد وحدة',
        'required' => 'يرجى اختيار مستأجر',
    ],
    'invoice' => [
        'section' => 'اختيار الفاتورة',
        'loading' => 'جارٍ تحميل الفواتير...',
        'unallocated' => 'دفعة غير مخصصة (غير مرتبطة بفاتورة محددة)',
        'none' => 'لا توجد فواتير مستحقة لهذا المستأجر',
        'due' => 'الاستحقاق: {date}',
        'due_na' => 'غير متوفر',
        'total_outstanding' => 'إجمالي المستحقات:',
        'required' => 'يرجى اختيار فاتورة أو وضع علامة كغير مخصصة',
    ],
    'details' => [
        'section' => 'تفاصيل الدفعة',
        'amount' => 'المبلغ *',
        'full' => 'كامل',
        'method' => 'طريقة الدفع *',
        'date' => 'تاريخ الدفع *',
        'reference' => 'المرجع (اختياري)',
        'reference_placeholder' => 'معرّف الإيصال/المعاملة',
        'notes' => 'ملاحظات (اختياري)',
        'notes_placeholder' => 'أي ملاحظات إضافية...',
    ],
    'overpayment' => [
        'title' => 'تم اكتشاف دفعة زائدة',
        'body' => 'هذا المبلغ يتجاوز رصيد الفاتورة بمقدار {amount}. سيُضاف الفائض إلى محفظة المستأجر.',
    ],
    'summary' => [
        'invoice_balance' => 'رصيد الفاتورة',
        'payment_amount' => 'مبلغ الدفعة',
        'remaining' => 'المتبقي',
    ],
    'cancel' => 'إلغاء',
    'submit' => 'تسجيل دفعة',
    'submitting' => 'جارٍ التسجيل...',
];
