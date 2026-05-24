<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances invoice/receipt/credit-note templates tab. Mirror en/sw/ar.
 */
return [
    'invoices' => [
        'heading' => 'قوالب الفواتير',
        'subtitle' => 'خصّص شكل فواتيرك عند إرسالها إلى المستأجرين',
        'empty_title' => 'لا توجد قوالب فواتير بعد',
        'empty_description' => 'أنشئ قالبك الأول لتخصيص شكل فواتيرك.',
    ],
    'receipts' => [
        'heading' => 'قوالب الإيصالات',
        'subtitle' => 'خصّص شكل إيصالات الدفع التي تظهر للمستأجرين',
        'empty_title' => 'لا توجد قوالب إيصالات بعد',
        'empty_description' => 'أنشئ قالبك الأول لتخصيص شكل إيصالات الدفع الخاصة بك.',
    ],
    'credit_notes' => [
        'heading' => 'قوالب إشعارات الدائن',
        'subtitle' => 'تستخدم إشعارات الدائن قالب الفاتورة الخاص بك مع تعديلات',
        'inheritance' => 'وراثة القالب',
        'using_invoice_template' => 'استخدام قالب الفاتورة',
        'inherit_body' => 'ترث إشعارات الدائن تلقائيًا إعدادات قالب الفاتورة الافتراضي الخاص بك. يتغير العنوان إلى "إشعار دائن" وتُعرض المبالغ كقيم سالبة.',
        'current_default' => 'القالب الافتراضي الحالي:',
        'none_selected' => 'لم يتم تحديد أي قالب',
        'edit_invoice_template' => 'تعديل قالب الفاتورة',
        'no_template_found' => 'لم يتم العثور على قالب فاتورة. أنشئ قالب فاتورة أولًا لاستخدامه مع إشعارات الدائن.',
        'create_invoice_template' => 'إنشاء قالب فاتورة',
    ],
    'new_template' => 'قالب جديد',
    'set_default' => 'تعيين كافتراضي',
    'edit' => 'تعديل',
    'create_template' => 'إنشاء قالب',
    'default_badge' => 'افتراضي',
    'design' => [
        'classic' => 'كلاسيكي',
        'modern' => 'عصري',
        'minimal' => 'بسيط',
        'professional' => 'احترافي',
    ],
    'features' => [
        'logo' => 'الشعار',
        'bank' => 'البنك',
        'qr' => 'QR',
        'water' => 'الماء',
        'arrears' => 'المتأخرات',
        'receipt_number' => 'إيصال #',
        'method' => 'الطريقة',
        'tenant' => 'المستأجر',
        'none' => 'لا إضافات',
        'separator' => ' • ',
    ],
];
