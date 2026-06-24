<?php

declare(strict_types=1);

/**
 * Slice-2 PR-2.2: management-agreement composer. Mirror en/sw/ar.
 */
return [
    'index' => [
        'title' => 'اتفاقيات الإدارة',
        'subtitle' => 'الاتفاقيات التي تديرها نيابة عن المالكين.',
        'new' => 'اتفاقية جديدة',
        'none' => 'لا توجد اتفاقيات بعد. أنشئ أول اتفاقية لتحديد شروط المالك.',
        'owner' => 'المالك',
        'status' => 'الحالة',
        'created' => 'أُنشئت',
    ],
    'compose' => [
        'title' => 'اتفاقية إدارة جديدة',
        'owner' => 'مالك العقار',
        'owner_placeholder' => 'اختر مالكًا...',
        'clauses' => 'البنود',
        'clauses_hint' => 'اختر الشروط. كل بند موضّح؛ بند الرسوم يحدد ما تتقاضاه.',
        'include' => 'تضمين',
        'required_clause' => 'مطلوب',
        'fee_type' => 'النوع',
        'fee_base' => 'الأساس',
        'fee_value' => 'القيمة',
        'fee_cadence' => 'الدورية',
        'preview' => 'معاينة الاتفاقية',
        'preview_empty' => 'اختر البنود لعرض الاتفاقية.',
        'submit' => 'حفظ المسودة',
        'cancel' => 'إلغاء',
    ],
    'show' => [
        'owner' => 'المالك',
        'status' => 'الحالة',
        'hash' => 'بصمة المستند',
        'back' => 'العودة إلى الاتفاقيات',
        'draft_note' => 'مسودة — لم تُرسل بعد إلى المالك للتوقيع.',
    ],
    'status' => [
        'draft' => 'مسودة',
        'sent' => 'مُرسلة',
        'signed' => 'موقَّعة',
        'active' => 'نشطة',
        'amending' => 'قيد التعديل',
        'terminated' => 'منتهية',
    ],
    'draft_created' => 'تم إنشاء مسودة الاتفاقية.',
    'errors' => [
        'duplicate_binding' => 'يمكن أن تتضمن الاتفاقية كل نوع من البنود مرة واحدة فقط.',
        'invalid_fee' => 'شروط رسوم الإدارة غير صالحة — تحقق من النوع والقيمة.',
        'missing_param' => 'أدخل تفاصيل ":field" لهذا البند.',
        'invalid_option' => 'القيمة ":field" غير مسموح بها لهذا البند.',
    ],
];
