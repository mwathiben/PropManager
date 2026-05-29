<?php

declare(strict_types=1);

return [
    'head_title' => 'Gateway preferences',
    'heading' => 'Gateway preferences',
    'description_prefix' => "Set each landlord's preferred payment gateway. ",
    'auto_label' => 'auto',
    'description_suffix' => ' means KES routes to Paystack and USD/EUR/GBP routes to Stripe. Forced choices override the currency rule for support cases.',
    'empty' => 'No landlords found.',
    'table' => [
        'landlord' => 'Landlord',
        'email' => 'Email',
        'paystack' => 'Paystack',
        'stripe' => 'Stripe',
        'preference' => 'Preference',
    ],
    'options' => [
        'auto' => 'auto (by currency)',
        'paystack' => 'Paystack',
        'stripe' => 'Stripe',
    ],
];
