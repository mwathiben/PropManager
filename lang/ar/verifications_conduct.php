<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant verification conduct page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'التحقق من {name}',
    'heading' => 'التحقق من المستأجر',
    'verify_subtitle' => 'التحقق من {name}',
    'manage_templates' => 'إدارة القوالب',
    'unit_line' => 'الوحدة {number} - {building}',
    'start' => [
        'title' => 'بدء التحقق',
        'description' => 'اختر قالب تحقق لبدء عملية التحقق لهذا المستأجر.',
        'select_template' => 'اختر القالب',
        'choose_template' => 'اختر قالبًا...',
        'option' => '{name} ({count} عناصر)',
        'option_default_suffix' => ' - افتراضي',
        'button' => 'بدء التحقق',
        'starting' => 'جارٍ البدء...',
        'no_templates' => 'لم يتم العثور على قوالب تحقق.',
        'create_one' => 'أنشئ واحدًا أولاً',
    ],
    'progress' => [
        'title' => 'تقدم التحقق',
        'percent_complete' => 'اكتمل {percent}%',
    ],
    'stats' => [
        'verified' => 'تم التحقق',
        'waived' => 'تم التنازل',
        'pending' => 'قيد الانتظار',
        'rejected' => 'مرفوض',
    ],
    'checklist' => [
        'title' => 'قائمة التحقق',
        'reset' => 'إعادة تعيين',
    ],
    'item' => [
        'required' => 'مطلوب',
        'note_prefix' => 'ملاحظة: {note}',
        'audit' => '{action} بواسطة {name} في {date}',
    ],
    'action_label' => [
        'verified' => 'تم التحقق',
        'waived' => 'تم التنازل',
        'rejected' => 'مرفوض',
        'updated' => 'تم التحديث',
    ],
    'title' => [
        'add_note' => 'إضافة ملاحظة',
        'verify' => 'تحقق',
        'reject' => 'رفض',
        'waive' => 'تنازل',
        'reset_pending' => 'إعادة التعيين إلى قيد الانتظار',
    ],
    'complete' => [
        'pending_notice' => '{count} عنصر/عناصر مطلوبة قيد الانتظار',
        'rejected_notice' => '، {count} مرفوض',
        'ready' => 'تم التحقق من جميع العناصر المطلوبة. جاهز للإكمال!',
        'button' => 'إكمال التحقق',
    ],
    'note_modal' => [
        'title' => 'إضافة ملاحظة',
        'placeholder' => 'أضف ملاحظة حول عنصر التحقق هذا...',
        'cancel' => 'إلغاء',
        'save' => 'حفظ الملاحظة',
    ],
    'alert' => [
        'select_template' => 'يرجى اختيار قالب',
        'required_first' => 'يجب التحقق من جميع العناصر المطلوبة أو التنازل عنها قبل الإكمال.',
    ],
    'confirm' => [
        'reset' => 'هل أنت متأكد أنك تريد إعادة تعيين التحقق؟ سيتم فقدان كل التقدم.',
        'complete' => 'إكمال التحقق لهذا المستأجر؟ سيؤدي ذلك إلى وضع علامة على العقد كمتحقق منه.',
    ],
];
