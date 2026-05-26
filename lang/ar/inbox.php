<?php

declare(strict_types=1);

return [
    'thread_created' => 'تم إنشاء سلسلة الرسائل.',
    'message_sent' => 'تم إرسال الرسالة.',
    'thread_locked' => 'تم قفل السلسلة.',
    'thread_archived' => 'تم أرشفة السلسلة.',
    'seen' => [
        'label' => 'تمت المشاهدة',
        'mark_all' => 'تحديد الكل كمقروء',
    ],
    'presence' => [
        'online' => 'متصل',
        'typing' => '{name} يكتب… | {name} يكتبون…',
    ],
    'search' => [
        'placeholder' => 'البحث في الرسائل…',
        'title' => 'البحث في الرسائل',
        'empty' => 'اكتب 3 أحرف على الأقل للبحث.',
        'no_results' => 'لا توجد رسائل تطابق "{term}".',
        'in_thread' => 'في {title}',
    ],
    'scan' => [
        'hint' => 'تُفحص المرفقات قبل الإرسال.',
        'blocked' => 'تم حظر مرفق بواسطة الماسح الضوئي للفيروسات ولم يُرسل.',
        'unavailable' => 'فحص المرفقات غير متاح مؤقتاً. يرجى المحاولة لاحقاً.',
    ],
    'attachment' => [
        'invalid_mime' => 'نوع المرفق غير مسموح به.',
        'too_large' => 'المرفق يتجاوز حد 5 ميجابايت.',
    ],
    'message' => [
        'spam_rejected' => 'تم رفض الرسالة كرسالة مزعجة محتملة. يرجى المراجعة وإعادة الإرسال.',
        'deleted_by_sender' => 'تم حذف الرسالة بواسطة المرسل.',
        'thread_locked_by_landlord' => 'تم قفل السلسلة بواسطة المالك.',
        'thread_unlocked_by_landlord' => 'تم فتح السلسلة بواسطة المالك.',
    ],
    'notification' => [
        'subject' => 'رسالة جديدة من :sender',
        'sender_unknown' => 'فريق العقار',
    ],

    'chat' => [
        'today' => 'اليوم',
        'yesterday' => 'أمس',
        'unread' => 'الرسائل غير المقروءة',
        'sent' => 'تم الإرسال',
        'placeholder' => 'اكتب رسالة…',
        'send' => 'إرسال',
        'attach' => 'إرفاق ملفات',
        'body_label' => 'نص الرسالة',
        'locked' => 'هذه السلسلة {status} ولا يمكنها استقبال رسائل جديدة.',
        'chars_remaining' => 'بقي حرف {count} | بقي {count} أحرف',
        'jump_latest' => 'الانتقال إلى الأحدث',
        'sending' => 'جارٍ الإرسال…',
        'retry' => 'اضغط لإعادة المحاولة',
        'reply' => 'رد',
        'replying_to' => 'الرد على {name}',
        'cancel_reply' => 'إلغاء الرد',
        'reactions' => [
            'add' => 'إضافة تفاعل',
            'react_with' => 'تفاعل بـ {emoji}',
            'pill_label' => '{emoji}، {count} تفاعلات',
        ],
        'attachment' => [
            'unavailable' => 'المرفق غير متوفر',
            'open_image' => 'فتح الصورة',
            'close' => 'إغلاق',
        ],
    ],

    'show' => [
        'head_title' => 'رسالة من {name}',
        'back' => 'العودة إلى صندوق الوارد',
        'replying_to' => 'الرد على: {subject}',
        'sent_at' => 'أُرسلت {date}',
        'auto_created_ticket' => 'تذكرة أُنشئت تلقائياً:',
        'mark_as_read' => 'تحديد كمقروء',
        'attachments' => 'المرفقات ({count})',
        'attachment_alt' => 'المرفق {number}',
        'reply_via' => 'الرد عبر {channel}',
        'reply_placeholder' => 'اكتب ردك...',
        'chars_remaining' => 'بقي {count} حرفاً',
        'sending' => 'جارٍ الإرسال...',
        'send_reply' => 'إرسال الرد',
    ],

    'title' => 'صندوق الوارد',
    'subtitle' => 'رسائل المستأجرين من واتساب والرسائل النصية',
    'unread_count' => '({count} غير مقروءة)',
    'mark_all_read' => 'تحديد الكل كمقروء',
    'confirm_mark_all_read' => 'تحديد جميع الرسائل كمقروءة؟',
    'search_placeholder' => 'ابحث باسم المستأجر أو الهاتف أو الرسالة...',
    'filter' => [
        'all' => 'كل الرسائل',
        'unread' => 'غير مقروءة',
        'processed' => 'مقروءة / تمت معالجتها',
    ],
    'table' => [
        'tenant' => 'المستأجر',
        'message' => 'الرسالة',
        'source' => 'المصدر',
        'status' => 'الحالة',
        'time' => 'الوقت',
        'actions' => 'الإجراءات',
    ],
    'status' => [
        'received' => 'غير مقروءة',
        'processed' => 'مقروءة',
        'action_taken' => 'تم اتخاذ إجراء',
        'ignored' => 'تم تجاهلها',
    ],
    'reply_prefix' => 'رد: {subject}',
    'ticket_label' => 'تذكرة #{id}',
    'mark_read_title' => 'تحديد كمقروء',
    'mark_read' => 'تحديد كمقروء',
    'view' => 'عرض',
    'empty' => [
        'title' => 'لا توجد رسائل',
        'description' => 'عندما يرد المستأجرون على الإشعارات عبر واتساب أو الرسائل النصية، ستظهر رسائلهم هنا.',
    ],
    'pagination' => [
        'previous' => 'السابق',
        'next' => 'التالي',
        'showing' => 'عرض {from} إلى {to} من {total} رسالة',
    ],
];
