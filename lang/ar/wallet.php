<?php

declare(strict_types=1);

/**
 * Phase-76 WALLET-DEEP wallet lang namespace (Arabic / MSA). Parity: keys
 * mirror en / sw exactly.
 */

return [
    'errors' => [
        'currency_mismatch' => 'لا يمكن تطبيق رصيد محفظة :wallet على التزام بعملة :target — يجب أن تتطابق العملات.',
    ],
    'settings' => [
        'title' => 'التطبيق التلقائي للمحفظة',
        'subtitle' => 'تحكّم في كيفية تطبيق رصيد محفظة المستأجر على الفواتير',
        'mode_label' => 'وضع التطبيق التلقائي',
        'mode_off' => 'إيقاف — لا تطبّق تلقائيًا أبدًا',
        'mode_on_invoice_create' => 'عند إنشاء الفاتورة',
        'mode_oldest_first_sweep' => 'مسح يومي (الأقدم غير المدفوعة أولًا)',
        'save' => 'حفظ الإعدادات',
        'saved' => 'تم حفظ إعدادات المحفظة.',
    ],
];
