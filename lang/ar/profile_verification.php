<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: user-profile document-verification tab. Mirror en/sw/ar.
 */
return [
    'status' => [
        'verified' => 'تم التحقق',
        'incomplete' => 'التحقق غير مكتمل',
        'verified_body' => 'تم التحقق من ملفك الشخصي',
        'incomplete_body' => 'أكمل جميع الحقول للتحقق من ملفك الشخصي',
    ],
    'fields' => [
        'phone' => 'رقم الهاتف',
        'national_id' => 'الهوية الوطنية',
        'emergency_contact' => 'جهة اتصال الطوارئ',
        'profile_photo' => 'صورة الملف الشخصي',
    ],
    'identity' => [
        'heading' => 'معلومات الهوية',
        'subtitle' => 'تفاصيل الاتصال والهوية الخاصة بك',
        'phone_label' => 'رقم الهاتف',
        'phone_placeholder' => '+254 712 345 678',
        'national_id_label' => 'الهوية الوطنية / جواز السفر',
        'national_id_placeholder' => 'أدخل رقم هويتك',
    ],
    'emergency' => [
        'heading' => 'جهة اتصال الطوارئ',
        'subtitle' => 'شخص يمكننا الاتصال به في حالة الطوارئ',
        'name_label' => 'اسم جهة الاتصال',
        'name_placeholder' => 'الاسم الكامل',
        'phone_label' => 'هاتف جهة الاتصال',
        'phone_placeholder' => '+254 712 345 678',
    ],
    'info' => [
        'heading' => 'لماذا نحتاج هذه المعلومات',
        'body' => 'تساعدنا معلومات التحقق الخاصة بك في الاحتفاظ بسجلات دقيقة والاتصال بك أو بجهة اتصال الطوارئ الخاصة بك عند الحاجة. هذه المعلومات مشفرة ومحفوظة بأمان.',
    ],
    'saved' => 'تم حفظ معلومات التحقق.',
    'saving' => 'جارٍ الحفظ...',
    'submit' => 'حفظ معلومات التحقق',
];
