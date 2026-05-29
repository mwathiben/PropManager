<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: super-admin platform billing settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'إعدادات فوترة المنصة',
    'stats' => [
        'monthly_revenue' => 'إيرادات هذا الشهر',
        'transactions' => 'المعاملات',
        'avg_fee_percent' => 'متوسط الرسوم %',
        'total_processed' => 'إجمالي المعالج',
    ],
    'tabs' => [
        'settings' => 'الإعدادات',
        'history' => 'سجل التغييرات',
    ],
    'billing_model' => [
        'heading' => 'نموذج الفوترة',
        'current_label' => 'الحالي:',
        'select_label' => 'اختر النموذج',
        'reason_label' => 'السبب (اختياري)',
        'reason_placeholder' => 'لماذا تغير نموذج الفوترة؟',
        'submit' => 'تحديث نموذج الفوترة',
        'submitting' => 'جارٍ التحديث...',
    ],
    'calculator' => [
        'heading' => 'حاسبة الرسوم',
        'amount_placeholder' => 'أدخل المبلغ',
        'calculate' => 'احسب',
        'gross_amount' => 'المبلغ الإجمالي:',
        'platform_fee' => 'رسوم المنصة ({percent}%):',
        'landlord_receives' => 'المالك يستلم:',
    ],
    'fees' => [
        'heading' => 'إعداد الرسوم',
        'transaction_fee_percent' => 'نسبة رسوم المعاملة %',
        'transaction_fee_hint' => 'النسبة المئوية المفروضة على كل معاملة',
        'minimum_fee' => 'الحد الأدنى للرسوم ({currency})',
        'maximum_fee' => 'الحد الأقصى للرسوم ({currency})',
        'maximum_fee_placeholder' => 'بدون حد أقصى',
        'fee_bearer' => 'متحمل الرسوم',
        'hybrid_discount' => 'خصم المشترك الهجين %',
        'hybrid_discount_hint' => 'تخفيض الرسوم للمشتركين (100 = بدون رسوم)',
        'reason_label' => 'السبب (اختياري)',
        'reason_placeholder' => 'لماذا تغير الرسوم؟',
        'submit' => 'حفظ إعدادات الرسوم',
        'submitting' => 'جارٍ الحفظ...',
    ],
    'history' => [
        'heading' => 'التغييرات الأخيرة',
        'reason_prefix' => 'السبب: {reason}',
        'empty' => 'لم يتم تسجيل أي تغييرات بعد',
    ],
];
