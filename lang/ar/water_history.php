<?php

declare(strict_types=1);

return [
    'filters' => [
        'all_buildings' => 'كل المباني',
        'all_status' => 'كل الحالات',
        'pending' => 'قيد الانتظار',
        'approved' => 'معتمد',
        'invoiced' => 'تم إصدار الفاتورة',
        'clear' => 'مسح عوامل التصفية',
    ],
    'table' => [
        'unit' => 'الوحدة',
        'reading' => 'القراءة',
        'date' => 'التاريخ',
        'status' => 'الحالة',
    ],
    'unit_prefix' => 'الوحدة {number}',
    'status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'معتمد',
        'invoiced' => 'تم إصدار الفاتورة',
    ],
    'empty' => [
        'title' => 'لم يتم العثور على قراءات',
        'description_filtered' => 'حاول تعديل عوامل التصفية الخاصة بك.',
        'description_default' => 'ستظهر قراءات العداد هنا بمجرد تسجيلها.',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من أصل {total} نتيجة',
    ],
];
