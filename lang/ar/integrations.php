<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: integrations settings tab. Mirror en/sw/ar.
 * Third-party/brand names (OCR provider names, Azure, API keys) stay literal.
 */
return [
    'title' => 'التكاملات',
    'subtitle' => 'اربط الخدمات الخارجية لتعزيز وظائف PropManager.',
    'ocr' => [
        'heading' => 'OCR (التعرف الضوئي على الحروف)',
        'description' => 'اقرأ قيم عداد المياه تلقائيًا من الصور',
        'enable' => 'تفعيل OCR',
        'select_provider' => 'اختر مزود OCR',
        'recommended' => 'موصى به',
        'setup_guide' => 'دليل الإعداد',
        'requires' => 'يتطلب: {requirements}',
        'none' => [
            'name' => 'بدون OCR (يدوي فقط)',
            'description' => 'تعطيل الكشف التلقائي عن القراءة. سيقوم المشرفون بإدخال القيم يدويًا فقط.',
        ],
        'api_key_required_label' => 'مفتاح API مطلوب:',
        'api_key_required_body' => 'تحتاج إلى التسجيل في {provider} والحصول على مفتاح API.',
        'get_api_key' => 'احصل على مفتاح API',
        'api_key_configured' => 'تم تكوين مفتاح API',
        'update_key' => 'تحديث المفتاح',
        'delete' => 'حذف',
        'api_key_label' => 'مفتاح API',
        'api_key_placeholder' => 'أدخل مفتاح API الخاص بك',
        'api_key_hint' => 'سيتم تشفير مفتاح API الخاص بك وتخزينه بأمان',
        'endpoint_label' => 'عنوان URL لنقطة النهاية',
        'auto_verify' => 'التحقق التلقائي من القراءات',
        'auto_verify_hint' => 'تحقق تلقائيًا مما إذا كانت قراءة OCR تطابق الإدخال اليدوي ضمن نطاق التسامح',
        'saving' => 'جارٍ الحفظ...',
        'save' => 'حفظ إعدادات OCR',
        'test_connection' => 'اختبار الاتصال',
        'delete_confirm' => 'هل أنت متأكد أنك تريد حذف مفتاح API هذا؟ ستحتاج إلى إعادة إدخاله لاستخدام OCR.',
    ],
    'coming_soon' => [
        'title' => 'المزيد من التكاملات قريبًا',
        'body' => 'بوابات الرسائل القصيرة وبرامج المحاسبة والمزيد',
    ],
];
