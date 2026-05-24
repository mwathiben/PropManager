<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenant detail page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'المستأجر: {name}',
        'back_to_tenants' => 'العودة إلى المستأجرين',
        'message' => 'رسالة',
        'edit_profile' => 'تعديل الملف الشخصي',

        'sections' => [
            'overview' => 'نظرة عامة',
            'lease' => 'تفاصيل عقد الإيجار',
            'payments' => 'المدفوعات',
            'documents' => 'المستندات',
            'notes' => 'الملاحظات',
            'contacts' => 'جهات اتصال الطوارئ',
            'activity' => 'النشاط',
        ],

        'status' => [
            'no_active_lease' => 'لا يوجد عقد إيجار نشط',
            'in_arrears' => 'متأخرات',
            'up_to_date' => 'محدّث',
            'active' => 'نشط',
            'inactive' => 'غير نشط',
        ],

        'contact_info' => [
            'title' => 'معلومات الاتصال',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'id_number' => 'رقم الهوية',
            'tenant_since' => 'مستأجر منذ',
        ],

        'stats' => [
            'unit' => 'الوحدة',
            'monthly_rent' => 'الإيجار الشهري',
            'deposit' => 'الوديعة',
            'arrears' => 'المتأخرات',
            'credit_balance' => 'الرصيد الدائن',
            'adjust' => 'تعديل',
        ],

        'primary_contact' => [
            'title' => 'جهة اتصال الطوارئ الرئيسية',
            'none' => 'لم يتم تعيين جهة اتصال رئيسية',
        ],

        'lease' => [
            'current_title' => 'عقد الإيجار الحالي',
            'property_building_unit' => 'العقار / المبنى / الوحدة',
            'property_fallback' => 'العقار',
            'building_fallback' => 'المبنى',
            'unit_prefix' => 'الوحدة',
            'lease_period' => 'فترة الإيجار',
            'ongoing' => 'مستمر',
            'monthly_rent' => 'الإيجار الشهري',
            'deposit_paid' => 'الوديعة المدفوعة',
            'service_charge' => 'رسوم الخدمة',
            'status_label' => 'الحالة',
            'rent_history' => 'سجل الإيجار',
            'no_active_title' => 'لا يوجد عقد إيجار نشط',
            'no_active_body' => 'لا يملك هذا المستأجر عقد إيجار نشط.',
            'past_leases' => 'عقود الإيجار السابقة',
            'per_month_suffix' => '/شهر',
        ],

        'payments' => [
            'recent_invoices' => 'الفواتير الأخيرة',
            'invoice_number' => 'الفاتورة #',
            'date' => 'التاريخ',
            'amount' => 'المبلغ',
            'status' => 'الحالة',
            'no_invoices' => 'لم يتم العثور على فواتير',
            'recent_payments' => 'المدفوعات الأخيرة',
            'no_payments' => 'لم يتم تسجيل أي مدفوعات',
        ],

        'documents' => [
            'title' => 'المستندات',
            'files_count' => '{count} ملف',
            'type_fallback' => 'أخرى',
            'view' => 'عرض',
            'download' => 'تنزيل',
            'none' => 'لم يتم رفع أي مستندات',
        ],

        'notes' => [
            'title' => 'ملاحظات خاصة',
            'add' => 'إضافة ملاحظة',
            'author_unknown' => 'غير معروف',
            'edit_aria' => 'تعديل الملاحظة',
            'delete_aria' => 'حذف الملاحظة',
            'none' => 'لا توجد ملاحظات بعد. أضف ملاحظتك الأولى حول هذا المستأجر.',
        ],

        'contacts' => [
            'title' => 'جهات اتصال الطوارئ',
            'add' => 'إضافة جهة اتصال',
            'primary_badge' => 'رئيسية',
            'edit_aria' => 'تعديل جهة اتصال الطوارئ',
            'delete_aria' => 'حذف جهة اتصال الطوارئ',
            'none' => 'لا توجد جهات اتصال طوارئ. أضف واحدة لهذا المستأجر.',
        ],

        'activity' => [
            'title' => 'الجدول الزمني للنشاط',
            'by' => 'بواسطة {name}',
            'system' => 'النظام',
            'none' => 'لم يتم تسجيل أي نشاط بعد.',
        ],

        'edit_modal' => [
            'title' => 'تعديل ملف المستأجر',
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'id_number' => 'رقم الهوية',
            'dob' => 'تاريخ الميلاد',
            'dob_hint' => '(اختياري — مطلوب لتدفق موافقة القاصر)',
            'minor_title' => 'قاصر — موافقة الوالدين مطلوبة',
            'minor_body' => 'يتطلب قانون حماية البيانات الكيني المادة 8 / القسم 33 موافقة أبوية يمكن التحقق منها قبل معالجة بيانات المستأجرين دون سن 18 عامًا.',
            'consent_url' => 'رابط مستند موافقة الوالدين',
            'consent_url_placeholder' => 'https://drive.example.com/consent.pdf',
            'consent_at' => 'تاريخ تقديم الموافقة',
            'consent_required_note' => 'يجب تقديم كل من رابط المستند والطابع الزمني قبل الحفظ.',
            'cancel' => 'إلغاء',
            'save' => 'حفظ التغييرات',
        ],

        'note_modal' => [
            'edit_title' => 'تعديل الملاحظة',
            'add_title' => 'إضافة ملاحظة',
            'label' => 'ملاحظة',
            'placeholder' => 'اكتب ملاحظتك هنا...',
            'pin' => 'تثبيت هذه الملاحظة',
            'cancel' => 'إلغاء',
            'save' => 'حفظ',
            'add' => 'إضافة ملاحظة',
        ],

        'contact_modal' => [
            'edit_title' => 'تعديل جهة الاتصال',
            'add_title' => 'إضافة جهة اتصال طوارئ',
            'name' => 'الاسم',
            'name_placeholder' => 'John Doe',
            'relationship' => 'صلة القرابة',
            'relationship_placeholder' => 'زوج، والد، أخ، إلخ.',
            'phone' => 'الهاتف',
            'phone_placeholder' => '+254 712 345 678',
            'email' => 'البريد الإلكتروني (اختياري)',
            'email_placeholder' => 'contact@example.com',
            'set_primary' => 'تعيين كجهة اتصال رئيسية',
            'cancel' => 'إلغاء',
            'save' => 'حفظ',
            'add' => 'إضافة جهة اتصال',
        ],

        'wallet_modal' => [
            'title' => 'تعديل رصيد المحفظة',
            'current_balance' => 'الرصيد الحالي',
            'adjustment_type' => 'نوع التعديل',
            'credit' => '+ إضافة رصيد',
            'debit' => '− خصم (إزالة)',
            'amount' => 'المبلغ ({currency})',
            'amount_placeholder' => 'أدخل المبلغ',
            'reason' => 'السبب',
            'reason_placeholder' => 'مثال: استرداد مبلغ زائد، رصيد حسن نية',
            'warning_label' => 'تحذير:',
            'warning_body' => 'مبلغ الخصم يتجاوز الرصيد الحالي. سيؤدي ذلك إلى رصيد سالب.',
            'cancel' => 'إلغاء',
            'add_credit' => 'إضافة رصيد',
            'remove_credit' => 'إزالة الرصيد',
        ],

        'confirm' => [
            'delete_note' => 'حذف هذه الملاحظة؟',
            'delete_contact' => 'حذف جهة اتصال الطوارئ هذه؟',
        ],
    ],

    'index' => [
        'head_title' => 'المستأجرون',
        'heading' => 'المستأجرون',
        'subtitle' => 'إدارة المستأجرين والدعوات',
        'invite_tenant' => 'دعوة مستأجر',
        'view' => 'عرض',
        'pending' => 'قيد الانتظار',
        'viewed' => 'تمت المشاهدة',
        'per_month' => '/شهر',
        'no_unit_assigned' => 'لم يتم تعيين وحدة',
        'unit_prefix' => 'الوحدة {number}',
        'unit_label' => 'الوحدة',
        'deposit_label' => 'الوديعة:',
        'start_label' => 'البداية:',
        'expires_label' => 'تنتهي:',

        'tabs' => [
            'active' => 'المستأجرون النشطون',
            'pending' => 'الدعوات قيد الانتظار',
            'past' => 'المستأجرون السابقون',
        ],

        'stats' => [
            'active_tenants' => 'المستأجرون النشطون',
            'pending_invites' => 'الدعوات قيد الانتظار',
            'monthly_rent' => 'الإيجار الشهري',
            'total_arrears' => 'إجمالي المتأخرات',
        ],

        'search' => [
            'placeholder' => 'البحث عن المستأجرين...',
            'pending_placeholder' => 'البحث بالاسم أو البريد الإلكتروني أو الهاتف...',
        ],

        'table' => [
            'tenant' => 'المستأجر',
            'contact' => 'جهة الاتصال',
            'unit' => 'الوحدة',
            'payment' => 'الدفع',
            'rent' => 'الإيجار',
            'actions' => 'الإجراءات',
            'tenant_info' => 'معلومات المستأجر',
            'lease_terms' => 'شروط الإيجار',
            'status' => 'الحالة',
            'last_unit' => 'آخر وحدة',
            'end_date' => 'تاريخ الانتهاء',
        ],

        'lease_status' => [
            'no_lease' => 'لا يوجد عقد إيجار',
            'active' => 'نشط',
            'inactive' => 'غير نشط',
        ],

        'payment_status' => [
            'na' => 'غير متاح',
            'arrears' => 'متأخرات',
            'up_to_date' => 'محدّث',
        ],

        'empty_active' => [
            'title' => 'لا يوجد مستأجرون نشطون',
            'description' => 'ادعُ المستأجرين للبدء.',
            'search' => 'جرّب مصطلح بحث مختلفًا.',
        ],

        'empty_pending' => [
            'title' => 'لا توجد دعوات قيد الانتظار',
            'description' => 'تم قبول جميع الدعوات أو انتهت صلاحيتها.',
            'search' => 'جرّب مصطلح بحث مختلفًا.',
        ],

        'empty_past' => [
            'title' => 'لا يوجد مستأجرون سابقون',
            'description' => 'سيظهر المستأجرون السابقون هنا بعد انتهاء عقد إيجارهم.',
            'search' => 'جرّب مصطلح بحث مختلفًا.',
        ],

        'pagination' => [
            'page_of' => 'الصفحة {current} من {total}',
            'previous' => 'السابق',
            'next' => 'التالي',
        ],

        'actions' => [
            'copy' => 'نسخ الرابط',
            'resend' => 'إعادة الإرسال',
            'edit' => 'تعديل',
            'cancel' => 'إلغاء',
        ],

        'confirm' => [
            'resend' => 'إعادة إرسال هذه الدعوة؟',
            'cancel' => 'هل أنت متأكد أنك تريد إلغاء هذه الدعوة؟',
        ],

        'alert' => [
            'copied' => 'تم نسخ رابط الدعوة!',
        ],
    ],
];
