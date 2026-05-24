<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub notifications tab. Mirror en/sw/ar.
 */
return [
    'setup' => [
        'title' => 'أكمل الإعداد',
        'body' => 'قم بتكوين إشعارات الرسائل القصيرة أو واتساب أو الإشعارات الفورية للوصول إلى المستأجرين عبر قنوات متعددة.',
        'go_to_settings' => 'الذهاب إلى الإعدادات',
    ],
    'stats' => [
        'total_sent' => 'إجمالي المُرسَل',
        'pending' => 'قيد الانتظار',
        'failed' => 'فشل',
        'this_month' => 'هذا الشهر',
    ],
    'quick_actions' => [
        'heading' => 'إجراءات سريعة',
        'send' => [
            'title' => 'إرسال إشعار',
            'subtitle' => 'الإرسال إلى مستأجر محدد',
        ],
        'bulk' => [
            'title' => 'إرسال جماعي',
            'subtitle' => 'الإرسال إلى عدة مستأجرين',
        ],
        'rent_reminders' => [
            'title' => 'إرسال تذكيرات الإيجار',
            'subtitle' => 'إخطار جميع المستأجرين بالإيجار القادم',
        ],
        'arrears_notices' => [
            'title' => 'إرسال إشعارات المتأخرات',
            'subtitle' => 'إخطار المستأجرين ذوي الأرصدة المستحقة',
        ],
    ],
    'channel_distribution' => [
        'heading' => 'توزيع القنوات',
        'empty' => 'لم يتم إرسال أي إشعارات بعد',
    ],
    'recent_activity' => [
        'heading' => 'النشاط الأخير',
        'view_all' => 'عرض الكل →',
        'empty_title' => 'لا توجد إشعارات بعد',
        'empty_subtitle' => 'أرسل إشعارك الأول للبدء',
        'recipient' => 'إلى: {name}',
        'unknown_recipient' => 'غير معروف',
    ],
    'full_center' => [
        'title' => 'مركز الإشعارات الكامل',
        'subtitle' => 'إدارة القوالب والجداول والإعدادات وعرض السجل الكامل',
        'open' => 'فتح المركز',
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
        'sms' => 'الرسائل القصيرة',
        'whatsapp' => 'واتساب',
        'push' => 'الإشعارات الفورية',
    ],
    'confirm' => [
        'rent_reminders' => 'إرسال تذكيرات الإيجار إلى جميع المستأجرين ذوي عقود الإيجار النشطة؟',
        'arrears_notices' => 'إرسال إشعارات المتأخرات إلى جميع المستأجرين ذوي الأرصدة المستحقة؟',
    ],
];
