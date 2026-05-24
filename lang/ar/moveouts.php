<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: move-out detail/settlement page. Mirror en/sw/ar.
 */
return [
    'show' => [
        'head_title' => 'إخلاء: {name}',
        'title' => 'عملية الإخلاء',
        'unit_prefix' => 'الوحدة',
        'cancel_process' => 'إلغاء عملية الإخلاء',
        'status_label' => [
            'notice_given' => 'تم تقديم الإشعار',
            'inspection_pending' => 'الفحص قيد التنفيذ',
            'inspection_complete' => 'اكتمل الفحص',
            'settlement_pending' => 'التسوية قيد الانتظار',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغى',
        ],
        'steps' => [
            'notice' => 'الإشعار',
            'move_out' => 'الإخلاء',
            'inspection' => 'الفحص',
            'settlement' => 'التسوية',
            'complete' => 'الإكمال',
        ],
        'start_inspection' => [
            'heading' => 'بدء الفحص',
            'description' => 'عندما يُخلي المستأجر الوحدة، أدخل تاريخ الإخلاء الفعلي لبدء عملية الفحص.',
            'date_label' => 'تاريخ الإخلاء الفعلي',
            'button' => 'بدء الفحص',
            'starting' => 'جارٍ البدء...',
        ],
        'deductions' => [
            'heading' => 'الفحص والخصومات',
            'add' => 'إضافة خصم',
            'auto' => 'تلقائي',
            'empty' => 'لا توجد خصومات مسجلة',
            'total' => 'إجمالي الخصومات',
            'edit_aria' => 'تعديل الخصم',
            'delete_aria' => 'حذف الخصم',
        ],
        'inspection_notes' => [
            'heading' => 'ملاحظات الفحص',
            'placeholder' => 'سجّل أي ملاحظات من الفحص...',
            'button' => 'إكمال الفحص',
            'completing' => 'جارٍ الإكمال...',
        ],
        'settlement_ready' => [
            'heading' => 'جاهز للتسوية',
            'description' => 'اكتمل الفحص. راجع الملخص المالي وسوِّ الوديعة.',
            'button' => 'تسوية الوديعة والإكمال',
        ],
        'completed' => [
            'heading' => 'اكتمل الإخلاء',
            'settled_via' => 'تمت التسوية في {date} عبر {method}',
            'reference' => 'المرجع: {reference}',
            'processed_by' => 'تمت المعالجة بواسطة: {name}',
        ],
        'financial' => [
            'heading' => 'الملخص المالي',
            'deposit_held' => 'الوديعة المحتجزة',
            'arrears_balance' => 'رصيد المتأخرات',
            'total_deductions' => 'إجمالي الخصومات',
            'refund_amount' => 'مبلغ الاسترداد',
        ],
        'details' => [
            'heading' => 'التفاصيل',
            'notice_date' => 'تاريخ الإشعار',
            'intended_move_out' => 'الإخلاء المقصود',
            'actual_move_out' => 'الإخلاء الفعلي',
        ],
        'confirm' => [
            'delete_deduction' => 'هل أنت متأكد أنك تريد إزالة هذا الخصم؟',
            'cancel_moveout' => 'هل أنت متأكد أنك تريد إلغاء هذا الإخلاء؟ سيبقى المستأجر في الوحدة.',
        ],
        'deduction_modal' => [
            'edit_title' => 'تعديل الخصم',
            'add_title' => 'إضافة خصم',
            'category_label' => 'الفئة (اختياري)',
            'custom_option' => 'خصم مخصص',
            'description_label' => 'الوصف *',
            'description_placeholder' => 'مثال: إصلاح ضرر الجدار',
            'amount_label' => 'المبلغ ({currency}) *',
            'notes_label' => 'ملاحظات (اختياري)',
            'cancel' => 'إلغاء',
            'saving' => 'جارٍ الحفظ...',
            'update' => 'تحديث',
            'add_button' => 'إضافة خصم',
        ],
        'settlement_modal' => [
            'title' => 'إكمال التسوية',
            'refund_to_tenant' => 'الاسترداد للمستأجر',
            'method_label' => 'طريقة التسوية *',
            'method_cash' => 'نقداً',
            'method_bank_transfer' => 'تحويل بنكي',
            'method_mobile_money' => 'الدفع عبر الهاتف (M-Pesa)',
            'method_offset' => 'المقاصة مقابل المتأخرات',
            'reference_label' => 'رقم المرجع (اختياري)',
            'reference_placeholder' => 'معرّف المعاملة أو رقم الإيصال',
            'warning' => 'سيؤدي هذا الإجراء إلى إنهاء العقد ووضع علامة على الوحدة كشاغرة.',
            'cancel' => 'إلغاء',
            'processing' => 'جارٍ المعالجة...',
            'complete' => 'إكمال الإخلاء',
        ],
    ],
];
