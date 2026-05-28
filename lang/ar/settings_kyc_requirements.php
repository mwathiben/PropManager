<?php

declare(strict_types=1);

return [
    'page_title' => 'متطلبات اعرف عميلك',
    'back_to_settings' => 'العودة إلى الإعدادات',
    'add_requirement' => 'إضافة متطلب',
    'edit_requirement' => 'تعديل المتطلب',
    'intro' => 'قم بتكوين متطلبات مستندات اعرف عميلك للمستأجرين لديك. الإعدادات الافتراضية للمنصة للقراءة فقط. يمكنك إضافة متطلبات مخصصة لجميع المباني أو لمبانٍ محددة.',
    'columns' => [
        'label' => 'التسمية',
        'type' => 'النوع',
        'scope' => 'النطاق',
        'required' => 'مطلوب',
        'active' => 'نشط',
        'actions' => 'الإجراءات',
    ],
    'scope' => [
        'platform_default' => 'افتراضي المنصة',
        'building_prefix' => 'المبنى: {name}',
        'all_buildings' => 'جميع المباني',
    ],
    'aria' => [
        'mark_not_required' => 'تمييز كغير مطلوب',
        'mark_required' => 'تمييز كمطلوب',
        'deactivate' => 'إلغاء تنشيط المتطلب',
        'activate' => 'تنشيط المتطلب',
    ],
    'actions' => [
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'read_only' => 'للقراءة فقط',
    ],
    'empty' => [
        'title' => 'لا توجد متطلبات',
        'subtitle' => 'ابدأ بإضافة متطلب اعرف عميلك.',
    ],
    'confirm_delete' => 'هل أنت متأكد أنك تريد حذف "{label}"؟ لا يمكن التراجع عن هذا الإجراء.',
    'form' => [
        'requirement_type' => 'نوع المتطلب *',
        'requirement_type_placeholder' => 'مثال: proof_of_income',
        'label' => 'التسمية *',
        'label_placeholder' => 'مثال: إثبات الدخل',
        'description' => 'الوصف',
        'description_placeholder' => 'تعليمات للمستأجر...',
        'building' => 'المبنى (اختياري)',
        'building_help' => 'اتركه فارغاً للتطبيق على جميع المباني',
        'all_buildings' => 'جميع المباني',
        'required' => 'مطلوب',
        'active' => 'نشط',
        'cancel' => 'إلغاء',
        'saving' => 'جارٍ الحفظ...',
        'update' => 'تحديث',
        'create' => 'إنشاء',
    ],
];
