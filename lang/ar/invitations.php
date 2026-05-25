<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker-invitation management page. Mirror en/sw/ar.
 */
return [
    'title' => 'دعوات الحُرّاس',
    'subtitle' => 'ادعُ وأدِر حُرّاس عقاراتك',
    'send' => 'إرسال دعوة',
    'table' => [
        'email' => 'البريد الإلكتروني للحارس',
        'property' => 'العقار',
        'sent_date' => 'تاريخ الإرسال',
        'status' => 'الحالة',
        'actions' => 'إجراءات',
    ],
    'accepted_at' => 'تم القبول {date}',
    'actions' => [
        'copy' => 'نسخ الرابط',
        'copy_title' => 'نسخ رابط الدعوة',
        'resend' => 'إعادة إرسال',
        'resend_title' => 'إعادة إرسال الدعوة',
        'cancel' => 'إلغاء',
        'cancel_title' => 'إلغاء الدعوة',
    ],
    'empty' => [
        'title' => 'لم تُرسل أي دعوات',
        'description' => 'ابدأ بإرسال دعوة إلى حارس.',
        'action' => 'إرسال أول دعوة',
    ],
    'modal' => [
        'title' => 'إرسال دعوة حارس',
        'email' => 'البريد الإلكتروني',
        'email_placeholder' => 'caretaker@example.com',
        'property' => 'العقار',
        'notice' => 'سيتلقى الحارس بريدًا إلكترونيًا يحتوي على رابط لقبول الدعوة وإنشاء حسابه. تنتهي صلاحية الدعوات بعد 30 يومًا.',
        'cancel' => 'إلغاء',
        'sending' => 'جارٍ الإرسال...',
    ],
    'toast' => [
        'title' => 'تم قبول الدعوة!',
        'message' => 'قبل {name} الدعوة الخاصة بـ {property}',
    ],
    'confirm' => [
        'resend' => 'إعادة إرسال هذه الدعوة؟',
        'cancel' => 'هل أنت متأكد أنك تريد إلغاء هذه الدعوة؟',
    ],
    'alert' => [
        'copied' => 'تم نسخ رابط الدعوة!',
    ],
];
