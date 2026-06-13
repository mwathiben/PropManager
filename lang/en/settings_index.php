<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: settings hub landing/tab shell. Mirror en/sw/ar.
 */
return [
    'title' => 'Settings',
    'subtitle' => 'Manage your business profile, payment methods, and system preferences',
    'tabs' => [
        'business' => 'Business Profile',
        'payment' => 'Payment Methods',
        'notifications' => 'Notifications',
        'integrations' => 'Integrations',
        'security' => 'Security',
        'branding' => 'Branding',
    ],
    'additional' => [
        'heading' => 'Additional Settings',
        'kyc_title' => 'KYC Requirements',
        'kyc_description' => 'Configure document requirements for tenant verification',
    ],
    'payment_hub_redirect' => [
        'heading' => 'Payment Methods & Credentials',
        'description' => 'Payment gateway credentials are now managed in the Payments Hub for a unified experience.',
        'panel_title' => 'Manage in Payments Hub',
        'panel_desc' => 'Configure accepted payment methods, gateway credentials (Paystack, M-Pesa, IntaSend), and bank details.',
        'button' => 'Go to Payments Hub',
    ],
];
