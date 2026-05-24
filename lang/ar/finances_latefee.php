<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: late-fee settings tab (Finances). Mirror en/sw/ar.
 */
return [
    'apply_now' => 'تطبيق رسوم التأخير الآن',
    'stats' => [
        'active_policies' => 'السياسات النشطة',
        'fees_this_month' => 'رسوم هذا الشهر',
        'total_applied' => 'إجمالي المطبق',
        'total_waived' => 'إجمالي المعفى',
    ],
    'policies' => [
        'title' => 'سياسات رسوم التأخير',
        'subtitle' => 'قم بتكوين قواعد رسوم التأخير التلقائية للفواتير المتأخرة',
        'add' => 'إضافة سياسة',
    ],
    'form' => [
        'name' => 'اسم السياسة *',
        'name_placeholder' => 'مثال: رسوم التأخير الافتراضية',
        'property' => 'العقار (اختياري)',
        'property_all' => 'جميع العقارات (افتراضي)',
        'building' => 'المبنى (اختياري)',
        'building_all' => 'جميع المباني',
        'grace_period' => 'فترة السماح (أيام) *',
        'grace_period_hint' => 'الأيام بعد تاريخ الاستحقاق قبل تطبيق الرسوم',
        'fee_type' => 'نوع الرسوم *',
        'fee_type_percentage' => 'نسبة مئوية (%)',
        'fee_type_fixed' => 'مبلغ ثابت ({currency})',
        'fee_percentage' => 'نسبة الرسوم *',
        'fee_amount' => 'مبلغ الرسوم *',
        'max_fee_cap' => 'الحد الأقصى للرسوم (اختياري)',
        'max_fee_cap_placeholder' => 'بلا حد',
        'compounding' => 'تراكمي (تطبيق الرسوم عدة مرات)',
        'frequency' => 'التكرار:',
        'frequency_daily' => 'يوميًا',
        'frequency_weekly' => 'أسبوعيًا',
        'frequency_monthly' => 'شهريًا',
        'active' => 'نشطة',
        'cancel' => 'إلغاء',
        'saving' => 'جارٍ الحفظ...',
        'update' => 'تحديث السياسة',
        'create' => 'إنشاء سياسة',
    ],
    'empty' => [
        'title' => 'لا توجد سياسات رسوم تأخير',
        'subtitle' => 'ابدأ بإنشاء سياسة رسوم تأخير.',
        'add_first' => 'أضف سياستك الأولى',
    ],
    'list' => [
        'status_active' => 'نشطة',
        'status_inactive' => 'غير نشطة',
        'grace_period' => 'فترة سماح {days} يوم',
        'compounds' => '| يتراكم {frequency}',
        'max' => '| الحد الأقصى {amount}',
        'deactivate' => 'إلغاء التنشيط',
        'activate' => 'تنشيط',
        'edit' => 'تعديل',
        'delete' => 'حذف',
    ],
    'delete' => [
        'title' => 'حذف السياسة',
        'confirm' => 'هل أنت متأكد أنك تريد حذف "{name}"؟ لا يمكن التراجع عن هذا الإجراء.',
        'cancel' => 'إلغاء',
        'confirm_btn' => 'حذف',
    ],
];
