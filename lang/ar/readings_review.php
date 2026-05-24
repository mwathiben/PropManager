<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: صفحة مراجعة قراءات المياه. Mirror en/sw/ar.
 */
return [
    'title' => 'مراجعة قراءات المياه',
    'pending_count' => '{count} قراءة معلّقة بانتظار الموافقة',
    'filters' => [
        'building' => 'المبنى',
        'all_buildings' => 'جميع المباني',
        'date_from' => 'من تاريخ',
        'date_to' => 'إلى تاريخ',
        'apply' => 'تطبيق',
        'reset' => 'إعادة تعيين',
    ],
    'empty' => [
        'title' => 'لا توجد قراءات معلّقة للمراجعة',
        'body' => 'تمت الموافقة على جميع القراءات أو رفضها',
    ],
    'card' => [
        'meter_photo' => 'صورة العدّاد',
        'meter_photo_alt' => 'صورة قراءة العدّاد',
        'no_photo' => 'لا توجد صورة متاحة',
        'reading_details' => 'تفاصيل القراءة',
        'unit' => 'الوحدة:',
        'building' => 'المبنى:',
        'reading_date' => 'تاريخ القراءة:',
        'recorded_by' => 'سُجّلت بواسطة:',
        'consumption_cost' => 'الاستهلاك والتكلفة',
        'previous_reading' => 'القراءة السابقة:',
        'manual_reading' => 'القراءة اليدوية:',
        'ocr_reading' => 'قراءة OCR:',
        'verified' => 'مُتحقَّق منها',
        'diff' => 'الفرق: {value}',
        'consumption' => 'الاستهلاك:',
        'consumption_value' => '{units} وحدة',
        'cost' => 'التكلفة:',
    ],
    'actions' => [
        'approve' => 'موافقة',
        'reject' => 'رفض',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من أصل {total} قراءة',
    ],
    'modal' => [
        'unit' => 'الوحدة:',
        'reading' => 'القراءة:',
        'cost' => 'التكلفة:',
        'cancel' => 'إلغاء',
    ],
    'approve' => [
        'title' => 'الموافقة على قراءة المياه',
        'notes_label' => 'ملاحظات (اختياري)',
        'notes_placeholder' => 'أضف أي ملاحظات حول هذه الموافقة...',
        'processing' => 'جارٍ الموافقة...',
        'confirm' => 'تأكيد الموافقة',
    ],
    'reject' => [
        'title' => 'رفض قراءة المياه',
        'reason_label' => 'سبب الرفض',
        'reason_placeholder' => 'اشرح سبب رفض هذه القراءة...',
        'reason_required' => 'يرجى تقديم سبب للرفض',
        'processing' => 'جارٍ الرفض...',
        'confirm' => 'تأكيد الرفض',
    ],
];
