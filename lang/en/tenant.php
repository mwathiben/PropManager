<?php

declare(strict_types=1);

/*
 * Phase-28 [TENANT-PORTAL] i18n keys for tenant-facing flash messages.
 * Swahili parity is enforced by Phase24CiTest.
 */

return [
    'profile' => [
        'updated' => 'Profile updated.',
        'password_updated' => 'Password updated.',
        'notifications_updated' => 'Notification preferences saved.',
    ],
    'statement' => [
        'title' => 'My Statement',
        'period_label' => 'Period :from to :to',
        'opening_balance' => 'Opening balance',
        'closing_balance' => 'Closing balance',
        'invoice_description' => 'Invoice :number',
        'payment_description' => 'Payment received',
        'col_date' => 'Date',
        'col_description' => 'Description',
        'col_reference' => 'Reference',
        'col_charge' => 'Charge',
        'col_payment' => 'Payment',
        'col_balance' => 'Balance',
        'emailed' => 'Your statement has been emailed to you.',
        'email_subject' => 'Your statement :from to :to',
        'email_heading' => 'Your account statement',
        'email_intro' => 'Hello :name, your statement is ready.',
        'email_footer' => 'Please contact your landlord if any line looks incorrect.',
    ],
];
