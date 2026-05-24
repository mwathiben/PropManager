<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: verification-templates management page. Mirror en/sw/ar.
 */
return [
    'title' => 'قوالب التحقق',
    'subtitle' => 'أنشئ قوائم تحقق للتحقق من المستأجرين الجدد',
    'new_template' => 'قالب جديد',
    'stats' => [
        'total' => 'إجمالي القوالب',
        'default' => 'القالب الافتراضي',
        'items_in_default' => 'العناصر في الافتراضي',
    ],
    'none' => 'لا يوجد',
    'default_badge' => 'افتراضي',
    'property_label' => 'العقار: {name}',
    'items_count' => '{count} عناصر تحقق',
    'more' => '+{count} المزيد',
    'actions' => [
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'required' => 'مطلوب',
        'add_item' => 'إضافة عنصر',
        'cancel' => 'إلغاء',
    ],
    'empty' => [
        'title' => 'لا توجد قوالب',
        'description' => 'أنشئ قالب تحقق للبدء.',
    ],
    'create' => [
        'title' => 'إنشاء قالب تحقق',
        'creating' => 'جارٍ الإنشاء...',
        'submit' => 'إنشاء قالب',
    ],
    'edit' => [
        'title' => 'تعديل القالب: {name}',
        'saving' => 'جارٍ الحفظ...',
        'submit' => 'حفظ التغييرات',
    ],
    'form' => [
        'name' => 'اسم القالب *',
        'name_placeholder' => 'مثال، تحقق قياسي',
        'property' => 'العقار (اختياري)',
        'all_properties' => 'جميع العقارات',
        'set_default' => 'تعيين كقالب افتراضي',
        'items_heading' => 'عناصر التحقق',
        'item_name_placeholder' => 'اسم العنصر *',
        'document_type' => 'نوع المستند',
        'description_placeholder' => 'الوصف (اختياري)',
    ],
    'confirm' => [
        'delete' => 'هل أنت متأكد أنك تريد حذف هذا القالب؟ لا يمكن التراجع عن ذلك.',
    ],
];
