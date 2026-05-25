<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notifications history tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'البحث حسب المستلم أو الموضوع...',
    'clear' => 'مسح',
    'status_options' => [
        'all' => 'جميع الحالات',
        'pending' => 'قيد الانتظار',
        'sent' => 'تم الإرسال',
        'delivered' => 'تم التسليم',
        'read' => 'مقروء',
        'failed' => 'فشل',
    ],
    'channel_options' => [
        'all' => 'جميع القنوات',
        'email' => 'البريد الإلكتروني',
        'sms' => 'رسالة نصية',
        'whatsapp' => 'واتساب',
        'push' => 'إشعار',
    ],
    'type_options' => [
        'all' => 'جميع الأنواع',
        'rent_reminder' => 'تذكير بالإيجار',
        'arrears_notice' => 'إشعار المتأخرات',
        'invoice' => 'فاتورة',
        'receipt' => 'إيصال',
        'rent_hike' => 'زيادة الإيجار',
        'lease_expiry' => 'انتهاء عقد الإيجار',
        'general' => 'عام',
    ],
    'table' => [
        'channel' => 'القناة',
        'recipient' => 'المستلم',
        'subject' => 'الموضوع',
        'type' => 'النوع',
        'status' => 'الحالة',
        'sent_at' => 'أُرسل في',
        'actions' => 'الإجراءات',
    ],
    'unknown' => 'غير معروف',
    'actions' => [
        'view_details' => 'عرض التفاصيل',
        'resend' => 'إعادة الإرسال',
    ],
    'empty' => [
        'title' => 'لم يتم العثور على إشعارات',
        'filtered' => 'حاول تعديل عوامل التصفية',
        'default' => 'ستظهر الإشعارات هنا بمجرد إرسالها',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من إجمالي {total} نتيجة',
    ],
    'detail' => [
        'title' => 'تفاصيل الإشعار',
        'subject' => 'الموضوع',
        'message' => 'الرسالة',
        'type' => 'النوع',
        'channel' => 'القناة',
        'sent_at' => 'أُرسل في',
        'delivered_at' => 'تم التسليم في',
        'error' => 'خطأ',
    ],
    'close' => 'إغلاق',
    'confirm' => [
        'resend' => 'إعادة إرسال هذا الإشعار؟',
    ],
];
