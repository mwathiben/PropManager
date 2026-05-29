<?php

declare(strict_types=1);

return [
    'head_title' => 'تفضيلات البوابة',
    'heading' => 'تفضيلات البوابة',
    'description_prefix' => 'حدد بوابة الدفع المفضلة لكل مالك. ',
    'auto_label' => 'تلقائي',
    'description_suffix' => ' يعني أن KES يُوجَّه إلى Paystack وأن USD/EUR/GBP يُوجَّه إلى Stripe. الخيارات المفروضة تتجاوز قاعدة العملة لحالات الدعم.',
    'empty' => 'لم يتم العثور على مالكين.',
    'table' => [
        'landlord' => 'المالك',
        'email' => 'البريد الإلكتروني',
        'paystack' => 'Paystack',
        'stripe' => 'Stripe',
        'preference' => 'التفضيل',
    ],
    'options' => [
        'auto' => 'تلقائي (حسب العملة)',
        'paystack' => 'Paystack',
        'stripe' => 'Stripe',
    ],
];
