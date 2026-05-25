<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications hub overview tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'أكمل الإعداد',
        'body' => 'قم بتهيئة إشعارات الرسائل القصيرة أو واتساب أو الإشعارات الفورية للوصول إلى المستأجرين عبر قنوات متعددة.',
        'run_wizard' => 'تشغيل معالج الإعداد',
    ],
    'stats' => [
        'total_sent' => 'إجمالي المرسل',
        'pending' => 'قيد الانتظار',
        'failed' => 'فشل',
        'this_month' => 'هذا الشهر',
    ],
    'quick_actions' => [
        'heading' => 'إجراءات سريعة',
        'send' => [
            'title' => 'إرسال إشعار',
            'description' => 'إرسال إلى مستأجر محدد',
        ],
        'bulk' => [
            'title' => 'إرسال جماعي',
            'description' => 'إرسال إلى عدة مستأجرين',
        ],
        'rent_reminders' => [
            'title' => 'إرسال تذكيرات الإيجار',
            'description' => 'إخطار جميع المستأجرين بالإيجار القادم',
        ],
        'arrears_notices' => [
            'title' => 'إرسال إشعارات المتأخرات',
            'description' => 'إخطار المستأجرين الذين لديهم أرصدة مستحقة',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'توزيع القنوات',
        'empty' => 'لم يتم إرسال أي إشعارات بعد',
    ],
    'recent_activity' => [
        'heading' => 'النشاط الأخير',
        'empty_title' => 'لا توجد إشعارات بعد',
        'empty_body' => 'أرسل إشعارك الأول للبدء',
        'recipient' => 'إلى: {name}',
        'unknown_recipient' => 'غير معروف',
    ],
    'confirm' => [
        'rent_reminders' => 'إرسال تذكيرات الإيجار إلى جميع المستأجرين الذين لديهم عقود إيجار نشطة؟',
        'arrears_notices' => 'إرسال إشعارات المتأخرات إلى جميع المستأجرين الذين لديهم أرصدة مستحقة؟',
    ],
    'types' => [
        'rent_reminder' => 'تذكير بالإيجار',
        'arrears_notice' => 'إشعار متأخرات',
        'invoice' => 'فاتورة',
        'receipt' => 'إيصال',
        'rent_hike' => 'زيادة الإيجار',
        'lease_expiry' => 'انتهاء عقد الإيجار',
        'general' => 'عام',
    ],
    'channels' => [
        'email' => 'البريد الإلكتروني',
        'sms' => 'رسالة قصيرة',
        'whatsapp' => 'واتساب',
        'push' => 'إشعار فوري',
    ],
];
