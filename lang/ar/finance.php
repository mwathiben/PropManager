<?php

declare(strict_types=1);

/**
 * Phase-81 FINANCE-DEPTH lang namespace. Parity: mirror en / sw / ar exactly.
 */

return [
    'deposit_settlement' => [
        'arrears_offset' => 'مقاصة المتأخرات',
        'refund_reason' => 'استرداد التأمين (تسوية المغادرة)',
        'move_out_reason' => 'تسوية المغادرة رقم :id',
        'received_reason' => 'تم استلام التأمين',
        'title' => 'تسوية التأمين',
        'held' => 'التأمين المحتجز',
        'deductions' => 'الخصومات',
        'arrears' => 'المتأخرات',
        'refund' => 'الاسترداد',
    ],
    'period_close' => [
        'blocked' => 'لا يمكن إغلاق هذه الفترة بعد.',
        'draft_invoices' => 'فواتير مسودة في هذه الفترة',
        'pending_reconciliation' => 'عناصر تسوية بنكية معلّقة',
        'ready' => 'جاهزة للإغلاق',
        'force' => 'الإغلاق على أي حال',
    ],
    'arrears' => [
        'aging' => [
            'title' => 'تقادم المتأخرات حسب المستأجر',
            'bucket_0_30' => '0–30 يومًا',
            'bucket_31_60' => '31–60 يومًا',
            'bucket_61_90' => '61–90 يومًا',
            'bucket_90_plus' => '90+ يومًا',
            'days_overdue' => 'أيام التأخر',
        ],
    ],
    'bank_recon' => [
        'imported' => 'تم استيراد :count معاملة، وتم تخطي :skipped.',
        'processed' => ':matched متطابقة، :unmatched غير متطابقة.',
        'matched' => 'تمت مطابقة الدفعة.',
    ],
    'late_fee' => [
        'applied' => 'تم تطبيق :count رسوم تأخير.',
        'projected' => 'رسوم التأخير المتوقعة إذا لم تُدفع بحلول :date',
        'apply_now' => 'تطبيق رسوم التأخير الآن',
    ],
];
