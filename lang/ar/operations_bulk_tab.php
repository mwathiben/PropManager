<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub bulk tab. Mirror en/sw/ar.
 */
return [
    'stats' => [
        'total_units' => 'إجمالي الوحدات',
        'occupied' => 'مشغولة',
        'active_leases' => 'عقود الإيجار النشطة',
        'buildings' => 'المباني',
    ],
    'operations' => [
        'rent_adjustment' => [
            'name' => 'تعديل الإيجار',
            'description' => 'زيادة أو تخفيض الإيجار لعدة وحدات في وقت واحد',
        ],
        'unit_status' => [
            'name' => 'تحديث حالة الوحدة',
            'description' => 'تحديث حالة عدة وحدات (شاغرة، صيانة، إلخ.)',
        ],
        'lease_management' => [
            'name' => 'إدارة عقود الإيجار',
            'description' => 'تمديد أو إنهاء عدة عقود إيجار في وقت واحد',
        ],
        'target_rent' => [
            'name' => 'تحديث الإيجار المستهدف',
            'description' => 'تحديث قيم إيجار السوق لعدة وحدات',
        ],
    ],
    'quick_select' => [
        'heading' => 'تحديد سريع حسب المبنى',
        'empty' => 'لا توجد مبانٍ متاحة.',
    ],
];
