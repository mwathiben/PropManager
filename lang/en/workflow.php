<?php

declare(strict_types=1);

/*
 * Phase-29 [WORKFLOW-AUTOMATION] i18n keys for landlord-driven
 * workflow notifications. Swahili parity enforced by Phase24CiTest.
 */

return [
    'rent_reminder' => [
        'subject' => 'Rent reminder for invoice :number',
        'body_before' => 'Your rent payment of KES :amount on invoice :number is due in :days day(s).',
        'body_due_today' => 'Your rent payment of KES :amount on invoice :number is due today.',
        'body_after' => 'Your rent payment of KES :amount on invoice :number is :days day(s) overdue. Please pay as soon as possible to avoid late fees.',
    ],
    'lease_renewal' => [
        'subject' => 'Lease renewal — :days day(s) remaining',
        'body' => 'Your lease ends on :end_date — :days day(s) from today. Please review the renewal terms.',
        'proposed' => 'Renewal terms proposed. The tenant will be notified.',
        'confirmed' => 'Renewal confirmed. Lease end date and rent updated.',
        'tenant_accepted' => 'You accepted the proposed renewal. Awaiting landlord confirmation.',
        'tenant_rejected' => 'You rejected the proposed renewal. The landlord will be notified.',
    ],
];
