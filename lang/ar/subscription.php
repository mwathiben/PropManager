<?php

declare(strict_types=1);

/**
 * i18n migration: subscription / billing management page. Mirror en/sw/ar.
 */
return [
    'title' => 'الاشتراك',
    'subtitle' => 'إدارة خطتك والفواتير',
    'view_plans' => 'عرض الخطط',
    'free' => 'مجاني',
    'plan_name' => 'خطة {name}',
    'per_cycle' => 'لكل {cycle}',
    'your_plan' => 'خطتك',
    'gateway_warning' => [
        'title' => 'نظام الدفع غير مهيأ',
        'body' => 'لم تتم تهيئة بوابة الدفع بعد. يمكنك عرض الخطط واستخدامك الحالي، لكن ترقيات الخطط المدفوعة غير متاحة مؤقتًا. يرجى الاتصال بالدعم إذا كنت بحاجة إلى مساعدة.',
    ],
    'cycle' => [
        'month' => 'شهر',
    ],
    'details' => [
        'billing_cycle' => 'دورة الفوترة',
        'ends_on' => 'ينتهي في',
        'next_billing' => 'تاريخ الفوترة التالي',
        'trial_ends' => 'تنتهي التجربة',
        'na' => 'غير متاح',
    ],
    'actions' => [
        'resume' => 'استئناف الاشتراك',
        'cancel' => 'إلغاء الاشتراك',
        'upgrade' => 'ترقية الخطة',
        'change' => 'تغيير الخطة',
    ],
    'usage' => [
        'heading' => 'الاستخدام',
        'subtitle' => 'استخدامك الحالي مقابل حدود الخطة',
        'at_limit' => 'لقد وصلت إلى الحد الأقصى',
        'near_limit' => 'تقترب من الحد الأقصى',
    ],
    'payments' => [
        'heading' => 'سجل المدفوعات',
        'line' => 'دفعة {plan}',
        'default_plan' => 'الاشتراك',
        'download' => 'تنزيل الإيصال',
        'empty' => 'لا يوجد سجل مدفوعات حتى الآن.',
    ],
    'cancel_modal' => [
        'title' => 'إلغاء الاشتراك؟',
        'intro' => 'هل أنت متأكد من رغبتك في إلغاء اشتراكك؟ يمكنك الاختيار:',
        'at_period_end' => 'الإلغاء في نهاية الفترة',
        'keep_until' => 'الاحتفاظ بالوصول حتى {date}',
        'immediately' => 'الإلغاء فورًا',
        'immediately_note' => 'فقدان الوصول على الفور (بدون استرداد)',
        'keep' => 'الاحتفاظ بالاشتراك',
        'cancelling' => 'جارٍ الإلغاء...',
        'confirm' => 'تأكيد الإلغاء',
    ],
];
