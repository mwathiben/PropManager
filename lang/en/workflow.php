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
];
