<?php

declare(strict_types=1);

/**
 * i18n: صفحة تحليلات قمع الإعداد في لوحة العمليات. Mirror en/sw/ar.
 */
return [
    'page_title' => 'قمع الإعداد',
    'header_title' => 'قمع الإعداد',
    'header_subtitle' => 'إكمال الخطوات حسب الدور + تحويل الدعوات (على مستوى المنصة)',
    'sessions_count' => '{count} جلسة',
    'complete_rate' => 'مكتمل {rate}%',
    'biggest_drop_at_step' => 'أكبر انخفاض في الخطوة {step}',
    'invitation_funnel' => 'قمع الدعوات',
    'acceptance_rate_label' => 'معدل القبول:',
    'roles' => [
        'landlord' => 'المالك',
        'caretaker' => 'الحارس',
        'tenant' => 'المستأجر',
        'water_client' => 'عميل المياه',
    ],
    'invite' => [
        'sent' => 'تم الإرسال',
        'viewed' => 'تمت المشاهدة',
        'accepted' => 'تم القبول',
        'pending' => 'قيد الانتظار',
        'expired' => 'منتهي الصلاحية',
    ],
    'step_labels' => [
        // Dynamic keys keyed by server-supplied label; raw label is used as fallback.
    ],
];
