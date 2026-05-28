<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: lease counter-offer negotiation history timeline.
 * Mirror en/sw/ar.
 */
return [
    'proposed_rent_label' => 'الإيجار المقترح:',
    'empty' => 'لا يوجد سجل عروض مضادة بعد.',
    'roles' => [
        'landlord' => 'المالك',
        'tenant' => 'المستأجر',
        'caretaker' => 'الحارس',
    ],
    'actions' => [
        'proposed' => 'اقترح',
        'countered' => 'قدّم عرضًا مضادًا',
        're_proposed' => 'أعاد الاقتراح',
        'accepted' => 'قبل',
        'rejected' => 'رفض',
        'expired' => 'انتهت صلاحيته',
    ],
    'time_ago' => [
        'just_now' => 'الآن',
        'minutes' => 'قبل {count} دقيقة',
        'hours' => 'قبل {count} ساعة',
        'days' => 'قبل {count} يوم',
    ],
];
