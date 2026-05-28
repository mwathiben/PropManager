<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out deduction categories settings page. Mirror en/sw/ar.
 */
return [
    'head_title' => 'فئات الخصومات',
    'title' => 'فئات الخصومات',
    'subtitle' => 'تكوين فئات الخصومات لعمليات تفتيش الإخلاء.',
    'add_category' => 'إضافة فئة',

    'breadcrumbs' => [
        'move_outs' => 'عمليات الإخلاء',
        'deduction_categories' => 'فئات الخصومات',
    ],

    'stats' => [
        'total' => 'الإجمالي',
        'active' => 'نشط',
        'auto_applied' => 'مطبقة تلقائيًا',
        'custom' => 'مخصص',
    ],

    'search' => [
        'placeholder' => 'البحث في الفئات...',
    ],

    'scope_filter' => [
        'all' => 'جميع النطاقات',
        'platform' => 'إعدادات المنصة الافتراضية',
        'custom' => 'فئاتك',
        'building' => 'خاص بالمبنى',
    ],

    'empty' => [
        'title' => 'لم يتم العثور على فئات',
        'try_different_search' => 'جرب مصطلح بحث مختلف.',
        'add_first' => 'أضف أول فئة خصم لك للبدء.',
    ],

    'sections' => [
        'platform_defaults' => 'إعدادات المنصة الافتراضية',
        'your_categories' => 'فئاتك',
        'building_specific' => 'خاص بالمبنى',
    ],

    'badges' => [
        'platform' => 'المنصة',
        'read_only' => 'للقراءة فقط',
        'all_buildings' => 'جميع المباني',
    ],

    'card' => [
        'auto_apply' => 'تطبيق تلقائي',
        'active' => 'نشط',
    ],

    'no_custom' => [
        'message' => 'لا توجد فئات مخصصة حتى الآن.',
        'create_first' => 'أنشئ فئتك الأولى',
    ],

    'modal' => [
        'title_new' => 'فئة جديدة',
        'title_edit' => 'تعديل الفئة',
        'name_label' => 'اسم الفئة',
        'name_placeholder' => 'مثال، رسوم التنظيف',
        'description_label' => 'الوصف',
        'description_placeholder' => 'وصف موجز لهذا الخصم',
        'default_amount_label' => 'المبلغ الافتراضي ({currency})',
        'scope_label' => 'النطاق',
        'all_buildings' => 'جميع المباني',
        'always_apply_label' => 'تطبيق دائمًا',
        'always_apply_help' => 'يُضاف تلقائيًا عند بدء التفتيش',
        'active_label' => 'نشط',
        'active_help' => 'متاح للاختيار',
        'cancel' => 'إلغاء',
        'saving' => 'جارٍ الحفظ...',
        'update' => 'تحديث',
        'create' => 'إنشاء',
    ],

    'delete_modal' => [
        'title' => 'حذف الفئة؟',
        'message' => 'سيؤدي هذا إلى الحذف الدائم لـ "{name}". الخصومات الحالية التي تستخدم هذه الفئة لن تتأثر.',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
    ],
];
