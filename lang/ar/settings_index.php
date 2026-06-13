<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: settings hub landing/tab shell. Mirror en/sw/ar.
 */
return [
    'title' => 'الإعدادات',
    'subtitle' => 'إدارة ملف عملك التجاري وطرق الدفع وتفضيلات النظام',
    'tabs' => [
        'business' => 'الملف التجاري',
        'payment' => 'طرق الدفع',
        'notifications' => 'الإشعارات',
        'integrations' => 'التكاملات',
        'security' => 'الأمان',
        'branding' => 'العلامة التجارية',
    ],
    'additional' => [
        'heading' => 'إعدادات إضافية',
        'kyc_title' => 'متطلبات اعرف عميلك',
        'kyc_description' => 'تكوين متطلبات المستندات للتحقق من المستأجر',
    ],
    'payment_hub_redirect' => [
        'heading' => 'طرق الدفع والبيانات الاعتمادية',
        'description' => 'تتم الآن إدارة بيانات اعتماد بوابة الدفع في مركز المدفوعات لتجربة موحدة.',
        'panel_title' => 'الإدارة في مركز المدفوعات',
        'panel_desc' => 'تكوين طرق الدفع المقبولة وبيانات اعتماد البوابة (Paystack وM-Pesa وIntaSend) والتفاصيل المصرفية.',
        'button' => 'الانتقال إلى مركز المدفوعات',
    ],
];
