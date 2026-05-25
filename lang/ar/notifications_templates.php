<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: notification message-templates tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'قوالب الإشعارات',
    'subheading' => 'أنشئ قوالب قابلة لإعادة الاستخدام لأنواع مختلفة من الإشعارات',
    'create' => 'إنشاء قالب',
    'empty' => [
        'title' => 'لا توجد قوالب بعد',
        'body' => 'أنشئ أول قالب إشعار للبدء',
    ],
    'default_badge' => 'افتراضي',
    'status' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ],
    'actions' => [
        'preview' => 'معاينة',
        'edit' => 'تعديل',
        'duplicate' => 'تكرار',
        'delete' => 'حذف',
        'cancel' => 'إلغاء',
    ],
    'modal' => [
        'edit_title' => 'تعديل القالب',
        'create_title' => 'إنشاء قالب',
        'name_label' => 'اسم القالب',
        'name_placeholder' => 'مثال: تذكير الإيجار الشهري',
        'type_label' => 'النوع',
        'placeholders_title' => 'العناصر النائبة المتاحة',
        'placeholders_hint' => 'انقر للإدراج في الموضوع أو النص',
        'subject_label' => 'الموضوع',
        'subject_placeholder' => "مثال: تذكير باستحقاق الإيجار لـ {'{{unit_name}}'}",
        'body_label' => 'نص الرسالة',
        'body_placeholder' => "عزيزي {'{{tenant_name}}'}،\n\nهذا تذكير بأن إيجارك البالغ {'{{rent_amount}}'} مستحق في {'{{due_date}}'}.\n\nمع أطيب التحيات،\n{'{{landlord_name}}'}",
        'is_active' => 'القالب نشط',
        'update_submit' => 'تحديث القالب',
        'create_submit' => 'إنشاء قالب',
    ],
    'preview' => [
        'title' => 'معاينة القالب',
        'subject' => 'الموضوع',
        'message' => 'الرسالة',
        'note' => 'تستخدم المعاينة بيانات نموذجية. سيتم استبدال القيم الفعلية عند الإرسال.',
    ],
    'types' => [
        'rent_reminder' => 'تذكير الإيجار',
        'arrears_notice' => 'إشعار المتأخرات',
        'invoice' => 'فاتورة',
        'receipt' => 'إيصال',
        'rent_hike' => 'زيادة الإيجار',
        'lease_expiry' => 'انتهاء عقد الإيجار',
        'general' => 'عام',
    ],
    'confirm_delete' => 'هل أنت متأكد أنك تريد حذف "{name}"؟',
    'copy_suffix' => ' (نسخة)',
    'sample' => [
        'tenant_name' => 'John Doe',
        'unit_name' => 'الوحدة A1',
        'payment_method' => 'M-Pesa',
        'landlord_name' => 'مدير العقارات',
        'property_name' => 'Sunrise Apartments',
    ],
];
