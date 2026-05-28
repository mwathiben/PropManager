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
    'caretaker_heading' => 'Caretaker Invitation',
    'tenant_heading' => 'Lease Invitation',
    'caretaker_body' => '{landlord} invited you to manage {property}',
    'tenant_body' => '{landlord} invited you to Unit {unit} at {property}',
    'rent_per_month' => 'Rent: {amount}/month',
    'expires' => 'Expires {date}',
    'accept' => 'Accept',
    'decline_title' => 'Decline invitation',
    'confirm_decline' => 'Are you sure you want to decline this invitation?',
];
