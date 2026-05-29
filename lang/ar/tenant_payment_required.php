<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration (Arabic): tenant initial-payment gating page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'الدفع مطلوب',
    'header_title' => 'الدفع الأولي مطلوب',
    'header_subtitle' => 'أكمل الدفع للوصول إلى بوابة المستأجر',
    'status' => [
        'pending_payment_title' => 'الدفع مطلوب',
        'pending_payment_message' => 'يرجى رفع إثبات الدفع أو الدفع عبر الإنترنت للمتابعة.',
        'verification_pending_title' => 'التحقق قيد الانتظار',
        'verification_pending_message' => 'تم إرسال إثبات الدفع الخاص بك وهو في انتظار التحقق من قبل المالك.',
        'rejected_title' => 'تم رفض التحقق',
        'rejected_default_message' => 'تم رفض إثبات الدفع الخاص بك. يرجى إعادة الإرسال.',
        'verified_title' => 'تم التحقق من الدفع',
        'verified_message' => 'تم التحقق من الدفع الخاص بك.',
    ],
    'unit_card' => [
        'heading' => 'وحدتك',
        'building_label' => 'المبنى',
        'unit_label' => 'الوحدة',
    ],
    'breakdown' => [
        'heading' => 'الدفع مطلوب',
        'security_deposit' => 'مبلغ التأمين',
        'first_month_rent' => 'إيجار الشهر الأول',
        'other_charges_default' => 'رسوم أخرى',
        'total_required' => 'الإجمالي المطلوب',
        'amount_paid' => 'المبلغ المدفوع',
        'balance_due' => 'الرصيد المستحق',
    ],
    'pay_online' => [
        'heading' => 'الدفع عبر الإنترنت',
        'description' => 'ادفع بأمان باستخدام بطاقتك أو المحفظة الإلكترونية. سيتم التحقق من الدفع تلقائيًا.',
        'cta' => 'ادفع {amount} الآن',
    ],
    'divider_or_upload' => 'أو ارفع إثبات الدفع',
    'upload' => [
        'heading' => 'رفع إثبات الدفع',
        'description' => 'إذا كنت قد أجريت تحويلًا مصرفيًا أو دفعًا عبر المحفظة الإلكترونية، فارفع الإثبات هنا.',
        'click_to_upload' => 'انقر للرفع',
        'click_to_upload_suffix' => 'أو اسحب وأفلت',
        'file_constraints' => 'PDF و JPG و PNG حتى 10 ميجابايت لكل ملف',
        'submit_idle' => 'إرسال للتحقق',
        'submit_processing' => 'جارٍ الرفع...',
        'errors' => [
            'invalid_type' => 'يُسمح فقط بملفات PDF و JPG و PNG.',
            'too_large' => 'يجب ألا يتجاوز حجم كل ملف 10 ميجابايت.',
        ],
    ],
    'submitted' => [
        'heading' => 'الوثائق المرسلة',
    ],
    'help' => [
        'heading' => 'بحاجة إلى مساعدة؟',
        'body' => 'إذا كانت لديك أسئلة حول الدفع أو تحتاج إلى مساعدة، يرجى التواصل مع مدير العقار.',
    ],
];
