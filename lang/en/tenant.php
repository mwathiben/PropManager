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
    'ticket_sla' => [
        'subject' => 'SLA breach: :title',
        'body' => 'Ticket ":title" (:priority) breached its SLA at :breached_at and has not yet had a first response.',
    ],
    'tickets' => [
        'annotation_saved' => 'Annotation saved.',
    ],
    'payment_plan' => [
        'submitted' => 'Payment plan request submitted (:count installments). Awaiting landlord approval.',
    ],
    'deposit_refund' => [
        'submitted' => 'Deposit refund request submitted. Your landlord will review it shortly.',
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
        'col_month' => 'Month',
        'col_charges' => 'Charges',
        'col_payments' => 'Payments',
        'col_net' => 'Net',
        'col_closing_balance' => 'Closing balance',
        'monthly_summary_title' => 'Monthly Summary',
        'preferences_saved' => 'Statement columns updated.',
        'emailed' => 'Your statement has been emailed to you.',
        'email_subject' => 'Your statement :from to :to',
        'email_heading' => 'Your account statement',
        'email_intro' => 'Hello :name, your statement is ready.',
        'email_footer' => 'Please contact your landlord if any line looks incorrect.',
    ],
];
