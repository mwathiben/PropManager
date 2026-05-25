<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: water-meter reading history page. Mirror en/sw/ar.
 */
return [
    'title' => 'سجل قراءات المياه',
    'heading' => 'سجل قراءات المياه',
    'add_readings' => 'إضافة قراءات',
    'filters' => [
        'title' => 'عوامل التصفية',
        'building' => 'المبنى',
        'all_buildings' => 'جميع المباني',
        'unit' => 'الوحدة',
        'all_units' => 'جميع الوحدات',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'status' => 'الحالة',
        'all' => 'الكل',
        'not_invoiced' => 'غير مفوتر',
        'invoiced' => 'مفوتر',
        'apply' => 'تطبيق عوامل التصفية',
        'clear' => 'مسح',
    ],
    'table' => [
        'date' => 'التاريخ',
        'unit' => 'الوحدة',
        'previous' => 'السابقة',
        'current' => 'الحالية',
        'consumption' => 'الاستهلاك',
        'cost' => 'التكلفة',
        'status' => 'الحالة',
        'actions' => 'الإجراءات',
    ],
    'cost_na' => 'غير متاح',
    'status' => [
        'invoiced' => 'مفوتر',
        'pending' => 'قيد الانتظار',
    ],
    'actions' => [
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'locked' => 'مقفل',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من {total} قراءة',
    ],
    'empty' => [
        'title' => 'لم يتم العثور على قراءات',
        'description' => 'حاول تعديل عوامل التصفية أو إضافة قراءات جديدة.',
    ],
    'confirm' => [
        'delete' => 'هل أنت متأكد أنك تريد حذف هذه القراءة؟',
    ],
];
