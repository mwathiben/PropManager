<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin users-list page. Mirror en/sw/ar.
 */
return [
    'title' => 'إدارة المستخدمين',
    'heading' => 'جميع المستخدمين',
    'search_placeholder' => 'ابحث بالاسم أو البريد الإلكتروني...',
    'all_roles' => 'جميع الأدوار',
    'filter' => 'تصفية',
    'empty' => 'لم يتم العثور على مستخدمين.',
    'table' => [
        'user' => 'المستخدم',
        'role' => 'الدور',
        'status' => 'الحالة',
        'joined' => 'تاريخ الانضمام',
        'actions' => 'الإجراءات',
    ],
    'status' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ],
    'actions' => [
        'activate' => 'تفعيل',
        'deactivate' => 'إلغاء التفعيل',
        'login_as' => 'تسجيل الدخول كـ',
    ],
    'pagination' => [
        'showing' => 'عرض {from} إلى {to} من أصل {total} نتيجة',
        'previous' => 'السابق',
        'next' => 'التالي',
    ],
    'confirm' => [
        'impersonate' => 'سيؤدي هذا إلى تسجيل دخولك كهذا المستخدم. هل تريد المتابعة؟',
        'toggle_status' => 'هل أنت متأكد أنك تريد تبديل حالة هذا المستخدم؟',
    ],
];
