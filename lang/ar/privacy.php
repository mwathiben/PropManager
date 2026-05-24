<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: privacy & data settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'إعدادات الخصوصية',
    'back_to_settings' => 'العودة إلى الإعدادات',
    'heading' => 'الخصوصية والبيانات',
    'subheading' => 'أدر بياناتك الشخصية ومارس حقوقك في الخصوصية.',
    'export' => [
        'heading' => 'تصدير بياناتك',
        'description_line1' => 'نزّل نسخة من جميع بياناتك الشخصية المخزنة في PropManager.',
        'description_line2' => 'يشمل ذلك معلومات ملفك الشخصي، وسجل الإيجار، والفواتير، والمدفوعات، والمستندات المرفوعة.',
        'legal_note' => 'بموجب المادة 20 من اللائحة العامة لحماية البيانات (GDPR) والمادة 26 من قانون حماية البيانات الكيني، لديك الحق في استلام بياناتك بصيغة قابلة للنقل.',
        'request_button' => 'طلب تصدير البيانات',
        'download_now' => 'تنزيل الآن',
        'modal_heading' => 'تصدير بياناتك',
        'modal_body_line1' => 'سنُجهّز ملف ZIP يحتوي على جميع بياناتك الشخصية. قد يستغرق ذلك بضع دقائق',
        'modal_body_line2' => 'للحسابات الأكبر. ستتلقى بريدًا إلكترونيًا عندما يصبح التصدير جاهزًا.',
        'requesting' => 'جارٍ الطلب...',
        'request_export' => 'طلب التصدير',
    ],
    'delete' => [
        'heading' => 'حذف حسابك',
        'scheduled_title' => 'الحذف مجدول',
        'scheduled_prefix' => 'حسابك مجدول للحذف في',
        'scheduled_suffix' => '.',
        'days_remaining_prefix' => 'لديك',
        'days_remaining_value' => '{days} يومًا',
        'days_remaining_suffix' => 'لإلغاء هذا الطلب.',
        'cancel_request' => 'إلغاء طلب الحذف ←',
        'blockers_intro' => 'حذف الحساب غير متاح بسبب ما يلي:',
        'normal_description_line1' => 'احذف حسابك وجميع البيانات المرتبطة به بشكل دائم.',
        'normal_description_line2' => 'لا يمكن التراجع عن هذا الإجراء بعد فترة السماح البالغة {days} يومًا.',
        'legal_note' => 'بموجب المادة 17 من اللائحة العامة لحماية البيانات (GDPR) والمادة 28 من قانون حماية البيانات الكيني، لديك الحق في المحو ("الحق في النسيان").',
        'request_button' => 'طلب حذف الحساب',
        'modal_heading' => 'حذف حسابك',
        'warning_label' => 'تحذير:',
        'warning_body' => 'سيؤدي هذا إلى حذف حسابك وجميع البيانات المرتبطة به بشكل دائم بعد فترة سماح مدتها {days} يومًا. لا يمكن التراجع عن هذا الإجراء.',
        'reason_label' => 'سبب المغادرة (اختياري)',
        'reason_placeholder' => 'ساعدنا على التحسين بمشاركة سببك...',
        'cancel' => 'إلغاء',
        'processing' => 'جارٍ المعالجة...',
        'confirm_button' => 'حذف حسابي',
    ],
    'rights' => [
        'heading' => 'حقوقك في البيانات',
        'access_label' => 'الوصول:',
        'access_body' => 'طلب نسخة من بياناتك الشخصية',
        'portability_label' => 'قابلية النقل:',
        'portability_body' => 'استلام بياناتك بصيغة قابلة للقراءة آليًا',
        'erasure_label' => 'المحو:',
        'erasure_body' => 'طلب حذف بياناتك الشخصية',
        'rectification_label' => 'التصحيح:',
        'rectification_body' => 'تصحيح البيانات غير الدقيقة عبر إعدادات ملفك الشخصي',
        'object_label' => 'الاعتراض:',
        'object_body' => 'إلغاء الاشتراك في الاتصالات التسويقية',
    ],
];
