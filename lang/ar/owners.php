<?php

declare(strict_types=1);

/**
 * Phase-101 OWNER-FOUNDATION. Mirror en / sw / ar exactly (parity-checked).
 */
return [
    'title' => 'ملّاك العقارات',
    'subtitle' => 'الملّاك الذين تدير العقارات نيابةً عنهم',
    'add' => 'إضافة مالك',
    'edit' => 'تعديل المالك',
    'none' => 'لا يوجد ملّاك بعد. أضف أول مالك تدير عقاراته.',
    'fields' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'الهاتف',
        'id_number' => 'رقم الهوية / التسجيل',
        'notes' => 'ملاحظات',
        'active' => 'نشط',
        'properties' => 'العقارات',
    ],
    'actions' => [
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'delete' => 'حذف',
        'assign' => 'تعيين',
        'unassign' => 'إلغاء التعيين',
        'email_statement' => 'إرسال البيان بالبريد',
        'download_statement' => 'تنزيل البيان',
    ],
    'assign' => [
        'title' => 'العقارات',
        'owner' => 'المالك',
        'unassigned' => 'غير معيَّن',
    ],
    'delete_confirm' => 'حذف هذا المالك؟ تبقى عقاراته، لكن دون تعيين.',
    'messages' => [
        'created' => 'تمت إضافة المالك.',
        'updated' => 'تم تحديث المالك.',
        'deleted' => 'تم حذف المالك؛ وأُلغي تعيين عقاراته.',
        'assigned' => 'تم تعيين العقار للمالك.',
        'unassigned' => 'تم إلغاء مالك العقار.',
        'statement_sent' => 'يتم إرسال البيان إلى :email.',
        'statement_no_email' => 'لا يوجد بريد إلكتروني لهذا المالك.',
    ],
];
