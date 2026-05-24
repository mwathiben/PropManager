<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finance hub reports tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'التقارير المالية',
    'subheading' => 'حلّل الإيرادات والمصروفات وأداء التحصيل',
    'filters_button' => 'المرشّحات',
    'export_formats' => [
        'xlsx' => 'Excel (.xlsx)',
        'pdf' => 'PDF',
        'csv' => 'CSV',
    ],
    'export_buttons' => [
        'rent_roll' => 'سجل الإيجارات',
        'property_pnl' => 'أرباح وخسائر العقار',
    ],
    'periods' => [
        'this_month' => 'هذا الشهر',
        'last_month' => 'الشهر الماضي',
        'this_quarter' => 'هذا الربع',
        'last_quarter' => 'الربع الماضي',
        'ytd' => 'منذ بداية العام',
        'this_fy' => 'السنة المالية الحالية',
        'last_fy' => 'السنة المالية الماضية',
        '12' => 'آخر 12 شهرًا',
        '6' => 'آخر 6 أشهر',
        '3' => 'آخر 3 أشهر',
        'custom' => 'نطاق مخصّص',
    ],
    'tools' => [
        'builder' => [
            'name' => 'منشئ التقارير',
            'desc' => 'أنشئ تقارير مخصّصة من بياناتك',
        ],
        'dashboards' => [
            'name' => 'لوحات المعلومات',
            'desc' => 'ابنِ لوحات معلومات من التقارير المحفوظة + المقاييس',
        ],
        'templates' => [
            'name' => 'القوالب',
            'desc' => 'انسخ تقريرًا منسّقًا للبدء',
        ],
        'scheduled' => [
            'name' => 'المجدولة',
            'desc' => 'أرسل التقارير بالبريد على وتيرة متكرّرة',
        ],
        'shares' => [
            'name' => 'الروابط المشتركة',
            'desc' => 'روابط للقراءة فقط لتقرير محفوظ',
        ],
        'metrics' => [
            'name' => 'مقاييس مخصّصة',
            'desc' => 'أنشئ صيغًا كأعمدة مشتقّة',
        ],
    ],
    'filters' => [
        'building' => 'المبنى',
        'all_buildings' => 'جميع المباني',
        'from' => 'من',
        'to' => 'إلى',
        'compare' => 'قارن بالفترة السابقة',
        'apply' => 'تطبيق',
        'clear' => 'مسح',
    ],
    'metrics' => [
        'total_invoiced' => 'إجمالي المفوتر',
        'total_collected' => 'إجمالي المُحصّل',
        'total_expenses' => 'إجمالي المصروفات',
        'avg_collection_rate' => 'متوسط معدّل التحصيل',
    ],
    'revenue' => [
        'title' => 'الإيرادات مقابل المصروفات',
        'net' => 'الصافي: {amount}',
        'invoiced' => 'المفوتر',
        'collected' => 'المُحصّل',
        'expenses' => 'المصروفات',
        'invoiced_tooltip' => 'المفوتر: {amount}',
        'collected_tooltip' => 'المُحصّل: {amount}',
        'expenses_tooltip' => 'المصروفات: {amount}',
        'empty_title' => 'لا توجد بيانات إيرادات',
        'empty_body' => 'حاول تعديل المرشّحات أو نطاق التاريخ',
    ],
    'collection' => [
        'title' => 'اتجاه معدّل التحصيل',
        'target' => 'الهدف 85%',
        'empty_title' => 'لا توجد بيانات تحصيل',
        'empty_body' => 'ستظهر البيانات عند إنشاء الفواتير',
    ],
    'occupancy' => [
        'title' => 'الإشغال حسب المبنى',
        'building' => 'المبنى',
        'units' => 'الوحدات',
        'occupied' => 'مشغولة',
        'vacant' => 'شاغرة',
        'rate' => 'المعدّل',
        'total' => 'الإجمالي',
        'empty_title' => 'لا توجد مبانٍ',
        'empty_body' => 'أضف عقارات لرؤية بيانات الإشغال',
    ],
    'arrears' => [
        'title' => 'أعمار المتأخرات',
        'total_outstanding' => 'إجمالي المستحقات',
        'buckets' => [
            'current' => 'حالية',
            '1-30' => '1-30 يومًا',
            '31-60' => '31-60 يومًا',
            '61-90' => '61-90 يومًا',
            '90+' => '90+ يومًا',
        ],
        'empty_title' => 'لا توجد متأخرات مستحقة',
        'empty_body' => 'جميع الفواتير مدفوعة في وقتها',
    ],
    'expenses_by_category' => [
        'title' => 'المصروفات حسب الفئة',
        'total' => 'الإجمالي',
        'expense_count' => 'مصروف {count} | {count} مصروفات',
        'empty_title' => 'لا توجد مصروفات مسجّلة',
        'empty_body' => 'ستظهر المصروفات هنا عند إضافتها',
    ],
    'water' => [
        'title' => 'استهلاك المياه',
        'units' => 'وحدة',
        'total_cost' => '{amount} إجمالي التكلفة',
        'top_consumers' => 'أكبر المستهلكين',
        'consumer_units' => '{count} وحدة',
        'empty' => 'لا توجد بيانات استهلاك مياه',
    ],
    'top_units' => [
        'title' => 'الوحدات الأفضل أداءً',
        'on_time' => '{onTime}/{total} في الوقت المحدّد',
        'empty_title' => 'لا توجد بيانات أداء',
        'empty_body' => 'تظهر البيانات عند إنشاء الفواتير',
    ],
];
