<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant payment/invoice history page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'سجل المدفوعات',
    'heading' => 'سجل المدفوعات',
    'subtitle' => 'عرض جميع مدفوعاتك وفواتيرك',
    'tabs' => [
        'payments' => 'المدفوعات',
        'invoices' => 'الفواتير',
    ],
    'columns' => [
        'date' => 'التاريخ',
        'amount' => 'المبلغ',
        'method' => 'الطريقة',
        'reference' => 'المرجع',
        'invoice_number' => 'رقم الفاتورة',
        'paid' => 'المدفوع',
        'status' => 'الحالة',
    ],
    'payments_empty' => [
        'title' => 'لا توجد مدفوعات بعد',
        'description' => 'سيظهر سجل مدفوعاتك هنا',
    ],
    'invoices_empty' => [
        'title' => 'لا توجد فواتير بعد',
        'description' => 'ستظهر فواتيرك هنا',
    ],
    'download_receipt' => 'تنزيل الإيصال',
];
