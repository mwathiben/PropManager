<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker home dashboard. Mirror en/sw/ar.
 */
return [
    'page_title' => 'لوحة تحكم الحارس',
    'header' => [
        'property_fallback' => 'العقار',
        'property_operations' => 'عمليات {name}',
        'buildings_assigned' => '{count} مبنى مُسند',
        'record_readings' => 'تسجيل القراءات',
    ],
    'action_items' => [
        'urgent_tickets_title' => 'تذاكر عاجلة',
        'urgent_tickets_description' => 'تتطلب اهتمامًا فوريًا',
        'no_urgent_title' => 'لا توجد مشكلات عاجلة',
        'no_urgent_description' => 'تم حل جميع التذاكر العاجلة',
        'open_tickets_title' => 'تذاكر مفتوحة',
        'open_tickets_description' => 'في انتظار الحل',
        'no_open_title' => 'لا توجد تذاكر مفتوحة',
        'no_open_description' => 'تم حل جميع التذاكر',
        'pending_readings_title' => 'قراءات معلقة',
        'pending_readings_description' => 'في انتظار الإدخال',
        'total_units_title' => 'إجمالي الوحدات',
        'total_units_subtitle' => '{count} مشغولة',
        'action_view' => 'عرض',
        'action_view_all' => 'عرض الكل',
        'action_input' => 'إدخال',
    ],
    'tasks' => [
        'heading' => 'مهام اليوم',
        'subtitle' => 'التذاكر المسندة إليك مرتبة حسب الأولوية',
        'view_all' => 'عرض الكل',
        'empty_title' => 'انتهيت من كل شيء!',
        'empty_subtitle' => 'لا توجد مهام مسندة إليك',
        'unit_label' => 'الوحدة {number} •',
    ],
    'quick_actions' => [
        'heading' => 'إجراءات سريعة',
        'input_readings_title' => 'إدخال قراءات المياه',
        'input_readings_subtitle' => 'تسجيل قراءات العداد الشهرية',
        'view_tickets_title' => 'عرض تذاكري',
        'view_tickets_subtitle' => '{count} تذكرة مفتوحة',
        'report_issue_title' => 'الإبلاغ عن مشكلة جديدة',
        'report_issue_subtitle' => 'إنشاء تذكرة صيانة',
    ],
    'unit_status' => [
        'heading' => 'نظرة عامة على حالة الوحدات',
        'occupied' => 'مشغولة',
        'vacant' => 'شاغرة',
        'maintenance' => 'صيانة',
        'total_units' => 'إجمالي الوحدات',
    ],
    'ticket_summary' => [
        'heading' => 'ملخص تذاكري',
        'resolved' => 'محلولة',
        'open' => 'مفتوحة',
        'total_assigned' => 'إجمالي المُسند',
    ],
    'landlord_contact' => [
        'heading' => 'جهة اتصال المالك',
    ],
];
