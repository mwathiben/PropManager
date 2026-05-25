<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: account-settings security tab (Settings/partials/SecurityTab).
 * Page-specific namespace, distinct from profile_security. Mirror en/sw/ar.
 */
return [
    'heading' => 'الأمان والخصوصية',
    'subheading' => 'إدارة أمان حسابك وإعدادات خصوصية البيانات.',
    'links' => [
        'two_factor' => [
            'title' => 'المصادقة الثنائية',
            'description' => 'أضف طبقة حماية إضافية إلى حسابك باستخدام المصادقة الثنائية',
        ],
        'password' => [
            'title' => 'كلمة المرور والملف الشخصي',
            'description' => 'حدّث كلمة المرور ومعلوماتك الشخصية',
        ],
        'privacy' => [
            'title' => 'الخصوصية والبيانات',
            'description' => 'تصدير أو حذف بياناتك الشخصية (الامتثال للائحة GDPR)',
        ],
    ],
    'status' => [
        'enabled' => 'مُفعّلة',
        'disabled' => 'معطّلة',
    ],
    'recommendations' => [
        'title' => 'توصيات الأمان',
        'enable_2fa' => 'تفعيل المصادقة الثنائية',
        'done' => 'تم',
        'strong_password' => 'استخدم كلمة مرور قوية وفريدة',
        'review_privacy' => 'راجع إعدادات خصوصية بياناتك بانتظام',
    ],
    'account_status' => [
        'title' => 'حالة أمان الحساب',
        'two_factor' => 'المصادقة الثنائية',
        'two_factor_protected' => 'حسابك محمي بالمصادقة الثنائية',
        'two_factor_not_enabled' => 'غير مُفعّلة - نوصي بتفعيل المصادقة الثنائية',
        'data_privacy' => 'خصوصية البيانات',
        'data_privacy_desc' => 'معالجة بيانات متوافقة مع لائحة GDPR',
    ],
];
