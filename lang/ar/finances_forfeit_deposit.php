<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: deposit forfeit modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'تمت مصادرة التأمين',
    'success_body' => 'تمت مصادرة التأمين.',
    'heading' => 'مصادرة التأمين',
    'warning' => 'سيؤدي هذا الإجراء إلى مصادرة كامل مبلغ التأمين. لا يمكن التراجع عن هذا.',
    'deposit_amount' => 'مبلغ التأمين',
    'tenant' => 'المستأجر',
    'unit' => 'الوحدة',
    'reason_label' => 'سبب المصادرة',
    'select_reason' => 'اختر سببًا',
    'cancel' => 'إلغاء',
    'forfeit_deposit' => 'مصادرة التأمين',
    'processing' => 'جارٍ المعالجة...',
    'reasons' => [
        'rent_arrears' => 'متأخرات إيجار مستحقة',
        'property_damage' => 'أضرار جسيمة بالعقار',
        'lease_violation' => 'مخالفة عقد الإيجار',
        'abandonment' => 'هجر العقار',
        'illegal_activity' => 'نشاط غير قانوني',
        'other' => 'أخرى',
    ],
    'errors' => [
        'reason_required' => 'يرجى تقديم سبب لمصادرة التأمين',
    ],
];
