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
    'caretaker_heading' => 'دعوة حارس',
    'tenant_heading' => 'دعوة عقد إيجار',
    'caretaker_body' => 'دعاك {landlord} لإدارة {property}',
    'tenant_body' => 'دعاك {landlord} إلى الوحدة {unit} في {property}',
    'rent_per_month' => 'الإيجار: {amount}/شهريًا',
    'expires' => 'تنتهي {date}',
    'accept' => 'قبول',
    'decline_title' => 'رفض الدعوة',
    'confirm_decline' => 'هل أنت متأكد أنك تريد رفض هذه الدعوة؟',
];
