<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub payments tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'البحث في المدفوعات...',
    'actions' => [
        'record_payment' => 'تسجيل دفعة',
        'bulk_import' => 'استيراد جماعي',
        'download_receipt' => 'تنزيل الإيصال',
        'refund' => 'استرداد',
    ],
    'columns' => [
        'reference' => 'المرجع',
        'tenant' => 'المستأجر',
        'invoice' => 'الفاتورة',
        'amount' => 'المبلغ',
        'method' => 'الطريقة',
        'date' => 'التاريخ',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'غير معروف',
        'no_unit' => 'غير متوفر',
    ],
    'empty' => [
        'title' => 'لا توجد مدفوعات',
        'description' => 'ستظهر المدفوعات هنا بمجرد تسجيلها',
    ],
];
