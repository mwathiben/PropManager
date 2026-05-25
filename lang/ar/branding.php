<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: white-label branding settings tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'العلامة التجارية',
    'intro' => 'خصّص كيفية ظهور فواتيرك وإيصالاتك للمستأجرين.',
    'logo' => [
        'heading' => 'شعار النشاط التجاري',
        'description' => 'سيظهر شعارك على الفواتير والإيصالات. الحجم الموصى به: 200x80 بكسل.',
        'alt' => 'شعار النشاط التجاري',
        'delete_title' => 'حذف الشعار',
        'click_to_upload' => 'انقر للرفع',
        'uploading' => 'جارٍ الرفع...',
        'change' => 'تغيير الشعار',
        'upload' => 'رفع الشعار',
        'accepted_formats' => 'الصيغ المقبولة: JPEG، PNG، GIF، SVG. الحجم الأقصى: 2 ميغابايت.',
        'size_error' => 'يجب أن يكون حجم ملف الشعار أقل من 2 ميغابايت',
        'delete_confirm' => 'هل أنت متأكد أنك تريد حذف شعار نشاطك التجاري؟',
    ],
    'numbering' => [
        'heading' => 'ترقيم الفواتير',
        'format_label' => 'صيغة رقم الفاتورة',
        'example' => '{format} (مثال: {example})',
        'legend' => '{yyyy} = السنة، {mm} = الشهر، {nnnn} = رقم تسلسلي',
    ],
    'footers' => [
        'heading' => 'تذييلات المستندات',
        'invoice_label' => 'نص تذييل الفاتورة',
        'invoice_placeholder' => 'مثال: شكرًا لتعاملك معنا. السداد مستحق خلال 7 أيام.',
        'invoice_help' => 'يظهر هذا النص في أسفل جميع الفواتير (500 حرف كحد أقصى)',
        'receipt_label' => 'نص تذييل الإيصال',
        'receipt_placeholder' => 'مثال: شكرًا على دفعتك. تم إنشاء هذا الإيصال تلقائيًا.',
        'receipt_help' => 'يظهر هذا النص في أسفل جميع إيصالات الدفع (500 حرف كحد أقصى)',
    ],
    'preview' => [
        'heading' => 'معاينة الفاتورة',
        'logo_alt' => 'شعار',
        'no_logo' => 'لا يوجد شعار',
        'company_name' => 'اسم شركتك',
        'invoice' => 'فاتورة',
        'footer_placeholder' => 'سيظهر نص تذييل فاتورتك هنا',
    ],
    'save' => [
        'saving' => 'جارٍ الحفظ...',
        'submit' => 'حفظ إعدادات العلامة التجارية',
    ],
];
