<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: payment refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'تم بدء الاسترداد!',
    'success_body' => 'تم إرسال طلب الاسترداد.',
    'heading' => 'بدء الاسترداد',
    'notice' => 'قد تستغرق عمليات الاسترداد من 3 إلى 5 أيام عمل للمعالجة حسب طريقة الدفع.',
    'payment_label' => 'الدفعة',
    'select_payment' => 'اختر دفعة',
    'already_refunded' => ' (تم الاسترداد بالفعل)',
    'original_amount' => 'المبلغ الأصلي',
    'payment_method' => 'طريقة الدفع',
    'refund_amount' => 'مبلغ الاسترداد',
    'full_amount' => 'المبلغ الكامل',
    'reason_label' => 'السبب',
    'select_reason' => 'اختر سببًا',
    'refund_method' => 'طريقة الاسترداد',
    'cancel' => 'إلغاء',
    'processing' => 'جارٍ المعالجة...',
    'methods' => [
        'original' => 'طريقة الدفع الأصلية',
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'mobile_money' => 'M-Pesa',
    ],
    'reasons' => [
        'overpayment' => 'دفع زائد',
        'duplicate' => 'دفعة مكررة',
        'moved_out' => 'انتقل المستأجر',
        'billing_error' => 'خطأ في الفوترة',
        'service_not_provided' => 'لم يتم تقديم الخدمة',
        'other' => 'أخرى',
    ],
    'errors' => [
        'select_payment' => 'الرجاء اختيار دفعة',
        'valid_amount' => 'الرجاء إدخال مبلغ صالح',
        'amount_exceeds' => 'لا يمكن أن يتجاوز المبلغ {max}',
        'select_reason' => 'الرجاء اختيار سبب',
        'select_method' => 'الرجاء اختيار طريقة الاسترداد',
        'failed' => 'فشل إنشاء الاسترداد',
    ],
];
