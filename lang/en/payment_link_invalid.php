<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: public invalid/expired/paid payment-link landing page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Payment Link',
    'heading_paid' => 'Invoice Already Paid',
    'heading_unavailable' => 'Link Unavailable',
    'sign_in' => 'Sign in to your account',
    'contact_landlord' => 'Contact your landlord if you believe this is an error.',
    'messages' => [
        'not_found' => 'This payment link is invalid or does not exist.',
        'revoked' => 'This payment link has been revoked.',
        'expired' => 'This payment link has expired.',
        'paid' => 'This invoice has already been paid. Thank you!',
        'unavailable' => 'This invoice is no longer available.',
    ],
];
