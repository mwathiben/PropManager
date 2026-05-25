<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: deposit refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'تم استرداد التأمين!',
    'success_body' => 'تمت معالجة استرداد التأمين.',
    'heading' => 'استرداد التأمين',
    'deposit_amount' => 'مبلغ التأمين',
    'tenant' => 'المستأجر',
    'unit' => 'الوحدة',
    'refund_amount' => 'مبلغ الاسترداد',
    'full_amount' => 'المبلغ الكامل',
    'deductions' => 'الخصومات (إن وجدت)',
    'reason_label' => 'سبب الخصومات',
    'select_reason' => 'اختر سببًا',
    'net_refund' => 'صافي الاسترداد للمستأجر',
    'cancel' => 'إلغاء',
    'process_refund' => 'معالجة الاسترداد',
    'processing' => 'جارٍ المعالجة...',
    'reasons' => [
        'unpaid_rent' => 'إيجار غير مدفوع',
        'property_damage' => 'أضرار بالعقار',
        'cleaning_fees' => 'رسوم التنظيف',
        'unpaid_utilities' => 'مرافق غير مدفوعة',
        'early_termination' => 'رسوم الإنهاء المبكر',
        'other' => 'أخرى',
    ],
    'errors' => [
        'amount_min' => 'يجب أن يكون مبلغ الاسترداد أكبر من 0',
        'amount_exceeds' => 'لا يمكن أن يتجاوز مبلغ الاسترداد {max}',
        'deductions_negative' => 'لا يمكن أن تكون الخصومات سالبة',
        'total_exceeds' => 'لا يمكن أن يتجاوز مبلغ الاسترداد مع الخصومات مبلغ التأمين',
        'reason_required' => 'يرجى تقديم سبب للخصومات',
    ],
];
