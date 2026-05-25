<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: scheduled-notifications tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'الإشعارات المجدولة',
    'subheading' => 'أتمتة تذكيرات الإيجار وإشعارات المتأخرات وتنبيهات انتهاء العقد',
    'create_schedule' => 'إنشاء جدول',
    'status' => [
        'active' => 'نشط',
        'paused' => 'متوقف مؤقتًا',
    ],
    'field' => [
        'type' => 'النوع',
        'trigger' => 'المُحفّز',
        'send_time' => 'وقت الإرسال',
        'channels' => 'القنوات',
    ],
    'next' => 'التالي: {value}',
    'last' => 'الأخير: {value}',
    'action' => [
        'run_now' => 'تشغيل الآن',
        'pause' => 'إيقاف مؤقت',
        'resume' => 'استئناف',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'cancel' => 'إلغاء',
    ],
    'empty' => [
        'title' => 'لا توجد جداول بعد',
        'body' => 'أنشئ جداول إشعارات تلقائية لإبقاء المستأجرين على اطّلاع',
    ],
    'modal' => [
        'edit_title' => 'تعديل الجدول',
        'create_title' => 'إنشاء جدول',
        'update' => 'تحديث الجدول',
        'create' => 'إنشاء جدول',
    ],
    'form' => [
        'name' => 'اسم الجدول',
        'name_placeholder' => 'مثال: تذكير بالإيجار قبل 3 أيام',
        'notification_type' => 'نوع الإشعار',
        'template' => 'القالب (اختياري)',
        'use_default' => 'استخدام الافتراضي',
        'trigger' => 'المُحفّز',
        'days' => 'الأيام',
        'send_time' => 'وقت الإرسال',
        'channels' => 'القنوات',
        'is_active' => 'الجدول نشط',
    ],
    'trigger_type' => [
        'days_before_due' => [
            'label' => 'أيام قبل استحقاق الإيجار',
            'description' => 'الإرسال قبل X يومًا من تاريخ استحقاق الإيجار',
        ],
        'days_after_overdue' => [
            'label' => 'أيام بعد التأخر',
            'description' => 'الإرسال بعد X يومًا من تأخر الإيجار',
        ],
        'days_before_expiry' => [
            'label' => 'أيام قبل انتهاء العقد',
            'description' => 'الإرسال قبل X يومًا من انتهاء العقد',
        ],
    ],
    'notification_type' => [
        'rent_reminder' => 'تذكير بالإيجار',
        'arrears_notice' => 'إشعار المتأخرات',
        'lease_expiry' => 'انتهاء العقد',
    ],
    'channel' => [
        'email' => 'البريد الإلكتروني',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'push' => 'إشعار فوري',
    ],
    'next_run' => [
        'paused' => 'متوقف مؤقتًا',
        'calculating' => 'جارٍ الحساب...',
    ],
    'last_run' => [
        'never' => 'أبدًا',
    ],
    'confirm' => [
        'delete' => 'هل أنت متأكد أنك تريد حذف "{name}"؟',
        'run' => 'تشغيل "{name}" الآن؟ سيؤدي ذلك إلى إرسال إشعارات إلى جميع المستأجرين المطابقين.',
    ],
];
