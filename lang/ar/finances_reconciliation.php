<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: bank reconciliation tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'تسوية الحساب البنكي',
    'subtitle' => 'استورد كشوف الحساب البنكية وطابق المعاملات مع الفواتير',
    'auto_match_all' => 'مطابقة الكل تلقائيًا',
    'import_statement' => 'استيراد كشف',
    'paystack' => [
        'heading' => 'تسوية Paystack',
        'last_run_failed' => 'فشل التشغيل الأخير: {message}',
        'status' => [
            'failed' => 'فشل',
            'discrepancies' => '{count} تعارضات',
            'clean' => 'سليم',
        ],
        'matched' => 'مطابقة',
        'local' => 'محلي',
        'remote' => 'بعيد',
        'discrepancies' => 'التعارضات',
    ],
    'import' => [
        'heading' => 'استيراد كشف الحساب البنكي',
        'bank_label' => 'البنك',
        'bank_placeholder' => 'اختر البنك...',
        'file_label' => 'ملف CSV/Excel',
        'file_hint' => 'الحد الأقصى 5 ميجابايت. المدعوم: CSV، XLSX، XLS',
        'column_mapping_toggle' => 'تعيين الأعمدة (اختياري)',
        'column_mapping_hint' => 'حدد أسماء الأعمدة إذا كانت تختلف عن الافتراضية (reference، amount، date، description)',
        'reference_column' => 'عمود المرجع',
        'amount_column' => 'عمود المبلغ',
        'date_column' => 'عمود التاريخ',
        'description_column' => 'عمود الوصف',
        'cancel' => 'إلغاء',
        'importing' => 'جارٍ الاستيراد...',
        'submit' => 'استيراد',
    ],
    'banks' => [
        'equity' => 'بنك Equity',
        'kcb' => 'بنك KCB',
        'coop' => 'بنك Co-operative',
        'stanbic' => 'بنك Stanbic',
        'absa' => 'بنك Absa',
        'ncba' => 'بنك NCBA',
        'dtb' => 'بنك DTB',
        'i_and_m' => 'بنك I&M',
        'family' => 'بنك Family',
        'other' => 'بنك آخر',
    ],
    'stats' => [
        'pending' => 'قيد الانتظار',
        'unmatched' => 'غير مطابقة',
        'matched' => 'مطابقة',
        'unmatched_amount' => 'المبلغ غير المطابق',
    ],
    'pending' => [
        'heading' => 'تسوية قيد الانتظار',
        'body' => 'لديك {count} دفعة تحتاج إلى مطابقتها مع الفواتير.',
    ],
    'reconciled' => [
        'heading' => 'تمت تسوية الكل',
        'body' => 'تمت مطابقة جميع الدفعات مع الفواتير. استورد كشف حساب بنكي لتسوية المعاملات الجديدة.',
    ],
    'table' => [
        'reference' => 'المرجع',
        'tenant' => 'المستأجر',
        'amount' => 'المبلغ',
        'method' => 'الطريقة',
        'date' => 'التاريخ',
        'empty_title' => 'لا توجد دفعات غير مطابقة',
        'empty_description' => 'استورد كشف حساب بنكي لبدء تسوية المعاملات',
        'match' => 'مطابقة',
    ],
    'placeholders' => [
        'reference' => 'reference',
        'amount' => 'amount',
        'date' => 'date',
        'description' => 'description',
    ],
    'fallback' => [
        'unknown_tenant' => 'غير معروف',
        'no_unit' => 'غير متوفر',
    ],
];
