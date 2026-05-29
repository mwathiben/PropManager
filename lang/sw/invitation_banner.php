<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: in-app InvitationBanner component shown to
 * authenticated users with pending caretaker/tenant invitations. Caller-
 * supplied details (inviter name, property, unit, rent amount, expiry
 * date) stay untranslated; only chrome (heading/action button/labels)
 * flows through here. Mirror en/sw/ar.
 */
return [
    'caretaker_heading' => 'Mwaliko wa Mlinzi',
    'tenant_heading' => 'Mwaliko wa Mkataba',
    'caretaker_body' => '{landlord} amekualika usimamie {property}',
    'tenant_body' => '{landlord} amekualika kwenye Chumba {unit} katika {property}',
    'rent_per_month' => 'Kodi: {amount}/mwezi',
    'expires' => 'Inaisha {date}',
    'accept' => 'Kubali',
    'decline_title' => 'Kataa mwaliko',
    'confirm_decline' => 'Una uhakika unataka kukataa mwaliko huu?',
];
