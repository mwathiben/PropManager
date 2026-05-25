<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub team-management tab. Mirror en/sw/ar.
 */
return [
    'header_title' => 'أعضاء الفريق',
    'header_subtitle' => 'إدارة الحراس ومديري العقارات',
    'invite_caretaker' => 'دعوة حارس',
    'active_caretakers' => 'الحراس النشطون',
    'no_active_caretakers' => 'لا يوجد حراس نشطون',
    'pending_invitations' => 'الدعوات المعلقة',
    'buildings_count' => '{count} مبانٍ',
    'expires' => 'تنتهي: {date}',
    'status' => [
        'pending' => 'معلق',
        'accepted' => 'مقبول',
        'expired' => 'منتهٍ',
        'declined' => 'مرفوض',
    ],
    'no_pending_invitations' => 'لا توجد دعوات معلقة',
    'actions' => [
        'copy_link' => 'نسخ الرابط',
        'resend' => 'إعادة الإرسال',
        'cancel' => 'إلغاء',
    ],
    'modal' => [
        'title' => 'دعوة حارس',
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'assign_buildings' => 'تعيين للمباني',
        'cancel' => 'إلغاء',
        'send_invitation' => 'إرسال الدعوة',
    ],
    'confirm' => [
        'resend' => 'إعادة إرسال هذه الدعوة؟',
        'cancel' => 'إلغاء هذه الدعوة؟',
        'remove_caretaker' => 'إزالة هذا الحارس؟ سيفقد إمكانية الوصول إلى عقاراتك.',
    ],
    'toast' => [
        'accepted' => '{name} قبل الدعوة!',
        'sent' => 'تم إرسال الدعوة بنجاح!',
        'resent' => 'تمت إعادة إرسال الدعوة!',
        'link_copied' => 'تم نسخ رابط الدعوة إلى الحافظة!',
        'copy_failed' => 'فشل نسخ الرابط',
    ],
];
