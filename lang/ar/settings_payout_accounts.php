<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: settings payout accounts page (Settings/PayoutAccounts).
 * Tenant/landlord payout destinations (M-Pesa, bank, etc.). Mirror en/sw/ar.
 */
return [
    'title' => 'حسابات الصرف',
    'header' => 'حسابات الصرف',
    'add_account' => 'إضافة حساب',
    'fee_banner' => [
        'heading' => 'معلومات رسوم المنصة',
        'billing_model_label' => 'نموذج الفوترة الحالي:',
        'fee_label' => 'رسوم المنصة:',
        'fee_per_transaction_suffix' => 'لكل معاملة',
        'description' => 'اربط حسابك المصرفي لاستلام المدفوعات مباشرةً. سيتم خصم رسوم المنصة تلقائيًا.',
    ],
    'billing_models' => [
        'transaction_fee' => 'رسوم المعاملة',
        'subscription' => 'اشتراك',
        'hybrid' => 'هجين',
    ],
    'alert' => [
        'heading' => 'حساب الصرف مطلوب',
        'description' => 'تحتاج إلى ربط حساب صرف مُوثَّق قبل أن يتمكن المستأجرون من إجراء المدفوعات عبر الإنترنت.',
    ],
    'badge' => [
        'primary' => 'أساسي',
    ],
    'actions' => [
        'set_primary' => 'تعيين كحساب صرف أساسي',
        'sync_status' => 'مزامنة حالة الحساب',
        'deactivate' => 'تعطيل حساب الصرف',
    ],
    'empty' => [
        'title' => 'لا توجد حسابات صرف',
        'description' => 'ابدأ بربط حسابك المصرفي.',
        'action' => 'إضافة حساب',
    ],
    'modal' => [
        'title' => 'إضافة حساب صرف',
        'business_name' => 'اسم النشاط التجاري',
        'business_name_placeholder' => 'اسم نشاطك التجاري أو عقارك',
        'bank' => 'البنك',
        'select_bank' => 'اختر بنكًا',
        'loading_banks' => 'جارٍ تحميل البنوك...',
        'account_number' => 'رقم الحساب',
        'account_number_placeholder' => 'أدخل رقم الحساب',
        'verify' => 'تحقّق',
        'verifying' => 'جارٍ التحقّق...',
        'verified_heading' => 'تم التحقّق من الحساب',
        'cancel' => 'إلغاء',
        'adding' => 'جارٍ الإضافة...',
        'submit' => 'إضافة حساب',
    ],
    'confirm' => [
        'deactivate' => 'هل أنت متأكد من رغبتك في تعطيل حساب الصرف هذا؟',
    ],
    'errors' => [
        'verify_failed' => 'تعذّر التحقّق من الحساب',
        'verify_exception' => 'فشل التحقّق من الحساب',
    ],
];
