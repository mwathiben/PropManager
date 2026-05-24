<?php

declare(strict_types=1);

/**
 * Phase-105 PORTFOLIO-HOME: the landlord landing (cross-property overview). Mirror en/sw/ar.
 */
return [
    'title' => 'المحفظة',
    'subtitle' => 'كل عقاراتك في لمحة واحدة',
    'kpi' => [
        'occupancy' => 'الإشغال',
        'rent_roll' => 'إجمالي الإيجار الشهري',
        'arrears' => 'المتأخرات المستحقة',
        'units' => 'الوحدات',
        'properties' => 'العقارات',
        'buildings' => 'المباني',
        'units_subtitle' => ':occupied من :total مشغولة',
        'properties_subtitle' => ':buildings مبانٍ',
    ],
    'actions' => [
        'title' => 'يحتاج إلى انتباه',
        'overdue_invoices' => 'فواتير متأخرة',
        'open_tickets' => 'تذاكر مفتوحة',
        'expiring_leases' => 'عقود تنتهي (60 يومًا)',
        'none' => 'لا شيء يحتاج إلى انتباه الآن.',
    ],
    'properties_heading' => 'عقاراتك',
    'card' => [
        'occupancy' => 'الإشغال',
        'rent_roll' => 'الإيجار',
        'arrears' => 'المتأخرات',
        'units' => ':occupied/:total وحدات',
        'open' => 'فتح اللوحة',
    ],
    'none' => 'لا توجد عقارات بعد. أضف أول عقار لك للبدء.',
];
