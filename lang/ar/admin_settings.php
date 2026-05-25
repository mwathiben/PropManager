<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin system settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'إعدادات النظام',
    'subtitle' => 'تكوين بوابة الدفع لمدفوعات الاشتراك',
    'back_to_dashboard' => 'العودة إلى لوحة التحكم',
    'email_sms' => [
        'heading' => 'تكوين البريد الإلكتروني والرسائل النصية',
        'intro' => 'يتم الآن تكوين إعدادات مزودي البريد الإلكتروني والرسائل النصية في',
        'notification_center' => 'مركز الإشعارات',
        'location' => 'ضمن العمليات > الإشعارات > الإعدادات.',
    ],
    'gateway' => [
        'title' => 'بوابة الدفع (Paystack)',
        'subtitle' => 'تكوين Paystack لمدفوعات الاشتراك',
        'configured' => 'تم التكوين',
        'not_configured' => 'لم يتم التكوين',
    ],
    'form' => [
        'public_key' => 'المفتاح العام',
        'public_key_hint' => 'اتركه فارغًا للاحتفاظ بالمفتاح الحالي',
        'secret_key' => 'المفتاح السري',
    ],
    'actions' => [
        'testing' => 'جارٍ الاختبار...',
        'test_connection' => 'اختبار الاتصال',
        'saving' => 'جارٍ الحفظ...',
        'save' => 'حفظ التغييرات',
    ],
    'errors' => [
        'secret_key_required' => 'يرجى إدخال المفتاح السري أولاً',
        'connection_failed' => 'فشل الاتصال: {message}',
    ],
];
