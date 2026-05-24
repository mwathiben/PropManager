<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'دعوات المستأجرين',
    'subtitle' => 'ادعُ مستأجرين جددًا إلى عقاراتك',
    'send' => 'إرسال دعوة',
    'no_vacant' => [
        'title' => 'لا توجد وحدات شاغرة',
        'body' => 'جميع وحداتك مشغولة. أخلِ وحدة أو أضف وحدات جديدة لإرسال دعوات المستأجرين.',
    ],
    'stats' => [
        'total' => 'إجمالي الدعوات',
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبولة',
    ],
    'status' => [
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبولة',
        'expired' => 'منتهية',
    ],
    'table' => [
        'tenant' => 'المستأجر',
        'unit' => 'الوحدة',
        'lease_terms' => 'شروط العقد',
        'status' => 'الحالة',
        'actions' => 'إجراءات',
    ],
    'pending_registration' => 'التسجيل قيد الانتظار',
    'unit_prefix' => 'الوحدة',
    'per_month' => '/شهر',
    'deposit_label' => 'الوديعة:',
    'start_label' => 'البداية:',
    'expires_label' => 'تنتهي:',
    'viewed' => 'تمت المشاهدة',
    'actions' => [
        'copy' => 'نسخ رابط الدعوة',
        'resend' => 'إعادة إرسال الدعوة',
        'edit' => 'تعديل الدعوة',
        'cancel' => 'إلغاء الدعوة',
        'cancel_btn' => 'إلغاء',
    ],
    'empty' => [
        'title' => 'لا توجد دعوات',
        'filtered' => 'لا توجد دعوات تطابق هذا المرشّح.',
        'get_started' => 'ابدأ بإرسال دعوة مستأجر.',
    ],
    'create' => [
        'title' => 'إرسال دعوة مستأجر',
        'sending' => 'جارٍ الإرسال...',
    ],
    'edit' => [
        'title' => 'تعديل الدعوة',
        'saving' => 'جارٍ الحفظ...',
        'save' => 'حفظ التغييرات',
    ],
    'form' => [
        'unit' => 'اختر الوحدة *',
        'unit_placeholder' => 'اختر وحدة شاغرة...',
        'email' => 'البريد الإلكتروني *',
        'email_placeholder' => 'tenant@example.com',
        'name' => 'اسم المستأجر',
        'name_placeholder' => 'John Doe',
        'phone' => 'رقم الهاتف',
        'phone_placeholder' => '+254 712 345 678',
        'lease_terms' => 'شروط العقد',
        'rent' => 'الإيجار الشهري ({currency}) *',
        'service_charge' => 'رسوم الخدمة',
        'deposit' => 'الوديعة ({currency}) *',
        'start_date' => 'تاريخ البدء *',
        'end_date' => 'تاريخ الانتهاء (اختياري)',
        'total_movein' => 'إجمالي تكلفة الانتقال',
        'movein_breakdown' => 'إيجار الشهر الأول + رسوم الخدمة + الوديعة',
        'send_via' => 'إرسال الدعوة عبر *',
        'notification_channels' => 'قنوات الإشعار',
    ],
    'channel' => [
        'email' => 'البريد الإلكتروني',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'not_configured' => '(غير مُهيّأ)',
    ],
    'confirm' => [
        'resend' => 'إعادة إرسال هذه الدعوة؟',
        'cancel' => 'هل أنت متأكد أنك تريد إلغاء هذه الدعوة؟ لا يمكن التراجع عن ذلك.',
    ],
    'alert' => [
        'copied' => 'تم نسخ رابط الدعوة!',
    ],
];
