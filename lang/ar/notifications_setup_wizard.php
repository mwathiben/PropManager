<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications setup wizard component. Mirror en/sw/ar.
 */
return [
    'steps' => [
        'welcome' => 'مرحبًا',
        'channels' => 'اختر القنوات',
        'email' => 'إعداد البريد الإلكتروني',
        'sms' => 'إعداد الرسائل القصيرة',
        'whatsapp' => 'إعداد واتساب',
        'push' => 'إعداد الإشعارات الفورية',
        'complete' => 'تم كل شيء!',
    ],
    'channel_options' => [
        'email_name' => 'البريد الإلكتروني',
        'email_desc' => 'أرسل عبر SMTP أو خدمة البريد',
        'sms_name' => 'الرسائل القصيرة',
        'sms_desc' => 'رسائل نصية عبر AT أو Twilio',
        'whatsapp_name' => 'واتساب',
        'whatsapp_desc' => 'رسائل عبر Twilio واتساب',
        'push_name' => 'فورية',
        'push_desc' => 'إشعارات المتصفح الفورية',
    ],
    'header' => [
        'step_progress' => 'الخطوة {current} من {total}',
    ],
    'welcome' => [
        'heading' => 'مرحبًا بك في الإشعارات',
        'intro' => 'لنقم بإعداد قنوات الإشعارات الخاصة بك. ستتمكن من إرسال تذكيرات الإيجار وإشعارات المتأخرات والمزيد عبر البريد الإلكتروني والرسائل القصيرة وواتساب والإشعارات الفورية.',
        'guide' => 'سيرشدك هذا المعالج خلال إعداد كل قناة. يمكنك تخطي أي قناة وإعدادها لاحقًا من علامة تبويب الإعدادات.',
    ],
    'channels' => [
        'intro' => 'اختر قنوات الإشعارات التي تريد إعدادها. يمكنك دائمًا إضافة المزيد لاحقًا.',
    ],
    'email' => [
        'intro' => 'قم بإعداد إعدادات البريد الإلكتروني لإرسال الإشعارات.',
        'mail_driver' => 'برنامج تشغيل البريد',
        'encryption' => 'التشفير',
        'driver_smtp' => 'SMTP',
        'driver_mailgun' => 'Mailgun',
        'driver_postmark' => 'Postmark',
        'driver_ses' => 'Amazon SES',
        'encryption_tls' => 'TLS',
        'encryption_ssl' => 'SSL',
        'encryption_none' => 'بدون',
        'smtp_host' => 'مضيف SMTP',
        'smtp_port' => 'منفذ SMTP',
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور',
        'from_address' => 'عنوان المُرسِل',
        'from_name' => 'اسم المُرسِل',
        'from_name_placeholder' => 'Property Manager',
    ],
    'sms' => [
        'intro' => 'قم بإعداد مزود الرسائل القصيرة لإرسال الرسائل النصية.',
        'provider' => 'مزود الرسائل القصيرة',
        'provider_africastalking' => "Africa's Talking",
        'provider_twilio' => 'Twilio',
        'username' => 'اسم المستخدم',
        'username_placeholder' => 'sandbox أو اسم المستخدم الخاص بك',
        'api_key' => 'مفتاح API',
        'sender_id' => 'معرّف المُرسِل (اختياري)',
        'sender_id_placeholder' => 'معرّف المُرسِل المعتمد الخاص بك',
        'account_sid' => 'معرّف الحساب SID',
        'auth_token' => 'رمز المصادقة',
        'from_number' => 'رقم المُرسِل',
    ],
    'whatsapp' => [
        'intro' => 'قم بإعداد Twilio واتساب لإرسال الرسائل.',
        'account_sid' => 'معرّف الحساب SID',
        'auth_token' => 'رمز المصادقة',
        'from_number' => 'رقم مُرسِل واتساب',
        'sandbox_hint' => 'استخدم رقم sandbox الخاص بـ Twilio واتساب للاختبار',
    ],
    'push' => [
        'intro' => 'قم بإعداد إشعارات Web Push.',
        'vapid_required' => 'مفاتيح VAPID مطلوبة',
        'vapid_explainer' => 'يتطلب Web Push مفاتيح VAPID للمصادقة. انقر أدناه لإنشاء المفاتيح تلقائيًا.',
        'generate_keys' => 'إنشاء مفاتيح VAPID',
        'vapid_subject' => 'موضوع VAPID (البريد الإلكتروني)',
        'vapid_subject_hint' => 'يجب أن يكون عنوان URL بصيغة mailto: أو https://',
    ],
    'complete' => [
        'heading' => 'أنت جاهز تمامًا!',
        'body' => 'تم إعداد قنوات الإشعارات الخاصة بك. يمكنك الآن إرسال تذكيرات الإيجار وإشعارات المتأخرات وإشعارات أخرى إلى مستأجريك.',
        'footer' => 'يمكنك تعديل هذه الإعدادات في أي وقت من علامة تبويب الإعدادات.',
    ],
    'footer' => [
        'back' => 'رجوع',
        'skip' => 'تخطّي هذه القناة',
        'get_started' => 'ابدأ',
        'continue' => 'متابعة',
        'complete_setup' => 'إكمال الإعداد',
    ],
    'alert' => [
        'vapid_generated' => 'تم إنشاء مفاتيح VAPID بنجاح!',
        'vapid_failed' => 'فشل إنشاء مفاتيح VAPID: {error}',
    ],
];
