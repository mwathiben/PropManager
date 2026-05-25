<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker work-queue (maintenance tickets) page. Mirror en/sw/ar.
 */
return [
    'title' => 'تذاكري',
    'heading' => 'قائمة مهامي',
    'subtitle' => 'إدارة التذاكر المسندة إليك',
    'stats' => [
        'urgent' => 'عاجل',
        'open' => 'مفتوح',
        'in_progress' => 'قيد التنفيذ',
        'resolved' => 'تم الحل',
    ],
    'filter_label' => 'تصفية:',
    'all_statuses' => 'جميع الحالات',
    'active_option' => 'نشط (مفتوح/قيد التنفيذ)',
    'all_priorities' => 'جميع الأولويات',
    'unit_prefix' => '- وحدة {number}',
    'reported_by' => 'أبلغ عنها {name}',
    'unknown_reporter' => 'غير معروف',
    'view' => 'عرض',
    'acknowledge' => 'إقرار',
    'start_work' => 'بدء العمل',
    'resolve' => 'حل',
    'empty' => [
        'title' => 'تم إنجاز كل شيء!',
        'description' => 'لا توجد تذاكر في قائمتك تطابق عوامل التصفية الحالية.',
    ],
    'pagination' => 'عرض {from} إلى {to} من {total} تذكرة',
    'time_ago' => [
        'days' => 'قبل {count} يوم',
        'hours' => 'قبل {count} ساعة',
        'just_now' => 'الآن',
    ],
    'resolve_modal' => [
        'title' => 'حل التذكرة',
        'notes_label' => 'ملاحظات الحل',
        'notes_placeholder' => 'صف ما تم القيام به لحل هذه المشكلة...',
        'resolving' => 'جارٍ الحل...',
        'submit' => 'وضع علامة كمحلولة',
        'cancel' => 'إلغاء',
    ],
];
