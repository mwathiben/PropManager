<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: bulk-operations hub landing/tab shell. Mirror en/sw/ar.
 */
return [
    'title' => 'العمليات المجمّعة',
    'subtitle' => 'إجراء تحديثات مجمّعة على الوحدات والعقود والإيجار',
    'tabs' => [
        'rent' => 'تعديل الإيجار',
        'status' => 'حالة الوحدة',
        'lease' => 'إدارة العقود',
        'target' => 'الإيجار المستهدف',
    ],
    'filters' => [
        'heading' => 'تصفية التحديد',
        'building_wing' => 'المبنى / الجناح',
        'all_buildings' => 'جميع المباني',
        'all_wings' => 'جميع الأجنحة',
        'property' => 'العقار',
        'all_properties' => 'جميع العقارات',
        'status' => 'الحالة',
        'all_statuses' => 'جميع الحالات',
        'found_label' => 'تم العثور على:',
        'units_suffix' => 'وحدات،',
        'with_active_leases' => 'بعقود نشطة',
        'strict_mode' => 'الوضع الصارم: العمليات مقصورة على المبنى/الجناح المحدد',
    ],
    'status' => [
        'vacant' => 'شاغرة',
        'occupied' => 'مشغولة',
        'maintenance' => 'صيانة',
        'arrears' => 'متأخرات',
    ],
];
