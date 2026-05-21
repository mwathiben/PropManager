<?php

declare(strict_types=1);

/**
 * Phase-75 parts lang namespace (Arabic / MSA). Parity contract: keys MUST
 * mirror en / sw exactly in order + nesting.
 */

return [
    'pricing' => [
        'title' => 'تسعير قطع الغيار',
        'subtitle' => 'تتبّع اتجاهات التكلفة وقارن المورّدين لكل قطعة',
        'empty' => 'لا توجد قطع نشطة بعد.',
        'current_cost' => 'التكلفة الحالية',
        'in_stock' => 'المتوفر',
        'history_title' => 'سجل التكلفة',
        'history_empty' => 'لم تُسجَّل أي تغييرات في التكلفة بعد.',
        'suppliers_title' => 'المورّدون',
        'suppliers_empty' => 'لم يُضف أي مورّد بعد.',
        'col_supplier' => 'المورّد',
        'col_unit_cost' => 'تكلفة الوحدة',
        'col_lead_time' => 'مدة التوريد',
        'col_min_order' => 'الحد الأدنى للطلب',
        'days' => '{count} يوم',
        'cheapest' => 'الأرخص',
        'fastest' => 'الأسرع',
        'add_supplier' => 'إضافة مورّد',
        'select_vendor' => 'اختر المورّد',
        'unit_cost_label' => 'تكلفة الوحدة',
        'lead_time_label' => 'مدة التوريد (أيام)',
        'min_order_label' => 'الحد الأدنى لكمية الطلب',
        'save' => 'حفظ المورّد',
        'remove' => 'إزالة',
        'flash' => [
            'supplier_saved' => 'تم حفظ المورّد.',
            'supplier_removed' => 'تمت إزالة المورّد.',
        ],
        'source' => [
            'manual' => 'يدوي',
            'purchase_order' => 'أمر شراء',
            'import' => 'استيراد',
        ],
    ],
    'forecast' => [
        'reason' => 'السبب',
        'reason_static' => 'أقل من الحد',
        'reason_lead_time_buffer' => 'احتياطي مدة التوريد',
        'stockout' => 'نفاد متوقع',
        'no_stockout' => 'لا استخدام حديث',
    ],
];
