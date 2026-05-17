<?php

declare(strict_types=1);

return [
    'profile' => [
        'updated' => '[TODO-ar] Profile updated.',
        'password_updated' => '[TODO-ar] Password updated.',
        'notifications_updated' => '[TODO-ar] Notification preferences saved.',
    ],
    'ticket_sla' => [
        'subject' => '[TODO-ar] SLA breach: :title',
        'body' => '[TODO-ar] Ticket ":title" (:priority) breached its SLA at :breached_at and has not yet had a first response.',
    ],
    'tickets' => [
        'annotation_saved' => '[TODO-ar] Annotation saved.',
    ],
    'payment_plan' => [
        'submitted' => '[TODO-ar] Payment plan request submitted (:count installments). Awaiting landlord approval.',
    ],
    'deposit_refund' => [
        'submitted' => '[TODO-ar] Deposit refund request submitted. Your landlord will review it shortly.',
    ],
    'statement' => [
        'title' => '[TODO-ar] My Statement',
        'period_label' => '[TODO-ar] Period :from to :to',
        'opening_balance' => '[TODO-ar] Opening balance',
        'closing_balance' => '[TODO-ar] Closing balance',
        'invoice_description' => '[TODO-ar] Invoice :number',
        'payment_description' => '[TODO-ar] Payment received',
        'col_date' => '[TODO-ar] Date',
        'col_description' => '[TODO-ar] Description',
        'col_reference' => '[TODO-ar] Reference',
        'col_charge' => '[TODO-ar] Charge',
        'col_payment' => '[TODO-ar] Payment',
        'col_balance' => '[TODO-ar] Balance',
        'col_month' => '[TODO-ar] Month',
        'col_charges' => '[TODO-ar] Charges',
        'col_payments' => '[TODO-ar] Payments',
        'col_net' => '[TODO-ar] Net',
        'col_closing_balance' => '[TODO-ar] Closing balance',
        'monthly_summary_title' => '[TODO-ar] Monthly Summary',
        'preferences_saved' => '[TODO-ar] Statement columns updated.',
        'emailed' => '[TODO-ar] Your statement has been emailed to you.',
        'email_subject' => '[TODO-ar] Your statement :from to :to',
        'email_heading' => '[TODO-ar] Your account statement',
        'email_intro' => '[TODO-ar] Hello :name, your statement is ready.',
        'email_footer' => '[TODO-ar] Please contact your landlord if any line looks incorrect.',
    ],
];
