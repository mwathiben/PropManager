<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease-creation (tenant invitation) page. Mirror en/sw/ar.
 */
return [
    'create' => [
        'title' => 'دعوة مستأجر',
        'heading' => 'دعوة مستأجر: الوحدة {unit}',
        'subheading' => 'إرسال دعوة إيجار للطابق {floor}',
        'success' => [
            'title' => 'تم إرسال الدعوة!',
            'sent_to' => 'تم إرسال دعوة إلى',
            'via' => 'عبر {channels}.',
            'follow_up' => 'سيتلقى المستأجر إشعارًا يحتوي على رابط لمراجعة شروط الإيجار وإنشاء حسابه.',
            'send_another' => 'إرسال دعوة أخرى',
            'return_dashboard' => 'العودة إلى لوحة التحكم',
        ],
        'how_it_works' => [
            'title' => 'كيف يعمل:',
            'step1' => 'أدخل البريد الإلكتروني للمستأجر وشروط الإيجار أدناه',
            'step2' => 'يتلقى المستأجر بريدًا إلكترونيًا يحتوي على رابط للمراجعة والقبول',
            'step3' => 'ينشئ المستأجر حسابه ويتم تفعيل الإيجار',
        ],
        'tenant_info' => [
            'title' => 'معلومات المستأجر',
            'subtitle' => 'أدخل تفاصيل الاتصال الخاصة بالمستأجر المحتمل',
        ],
        'fields' => [
            'email' => 'عنوان البريد الإلكتروني',
            'email_placeholder' => 'tenant@example.com',
            'email_help' => 'سيتم إرسال الدعوة إلى هذا البريد الإلكتروني',
            'name' => 'الاسم الكامل (اختياري)',
            'name_placeholder' => 'John Doe',
            'name_help' => 'يمكن للمستأجر تحديث هذا عند القبول',
            'phone' => 'رقم الهاتف',
            'phone_optional' => '(اختياري)',
            'phone_placeholder' => '+254 7XX XXX XXX',
            'phone_required_help' => 'مطلوب لتسليم SMS/WhatsApp',
            'monthly_rent' => 'الإيجار الشهري ({currency})',
            'service_charge' => 'رسوم الخدمة ({currency})',
            'service_charge_help' => 'القمامة، الأمن، الإنارة',
            'security_deposit' => 'وديعة التأمين ({currency})',
            'amount_placeholder' => '0.00',
            'start_date' => 'تاريخ بدء الإيجار',
            'end_date' => 'تاريخ انتهاء الإيجار (اختياري)',
            'end_date_help' => 'اتركه فارغًا للإيجار الشهري المتجدد',
        ],
        'lease_terms' => [
            'title' => 'شروط الإيجار',
            'subtitle' => 'حدد مبالغ الإيجار والوديعة لهذا الإيجار',
        ],
        'totals' => [
            'move_in' => 'إجمالي المستحق للانتقال:',
        ],
        'lease_period' => [
            'title' => 'فترة الإيجار',
        ],
        'channels' => [
            'title' => 'إرسال الدعوة عبر',
            'subtitle' => 'اختر كيفية إخطار المستأجر بهذه الدعوة',
            'email' => 'البريد الإلكتروني',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'not_configured' => 'غير مهيأ - قم بالإعداد في الإعدادات',
            'enter_phone' => 'أدخل رقم الهاتف أعلاه',
            'cost_warning' => 'قد تتكبد رسائل SMS وWhatsApp رسومًا بناءً على إعدادات مزود الخدمة الخاص بك.',
        ],
        'required' => '*',
        'cancel' => 'إلغاء',
        'sending' => 'جارٍ الإرسال...',
        'send' => 'إرسال الدعوة',
    ],
];
