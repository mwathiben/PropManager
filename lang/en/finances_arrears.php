<?php

declare(strict_types=1);

return [
    'metric' => [
        'total_arrears' => 'Total Arrears',
        'tenants_in_arrears' => 'Tenants in Arrears',
        'overdue_invoices' => 'Overdue Invoices',
    ],
    'aging' => [
        'title' => 'Aging Breakdown',
        '0_30' => '0-30 days',
        '31_60' => '31-60 days',
        '61_90' => '61-90 days',
        '90_plus' => '90+ days',
    ],
    'search_placeholder' => 'Search tenants...',
    'send_notices' => 'Send Arrears Notices',
    'columns' => [
        'invoice' => 'Invoice',
        'tenant' => 'Tenant',
        'balance' => 'Balance',
        'days_overdue' => 'Days Overdue',
        'due_date' => 'Due Date',
    ],
    'empty' => [
        'title' => 'No arrears',
        'description' => 'Great news! No overdue invoices.',
    ],
    'days_count' => '{count} days',
    'reminder_title' => 'Send Reminder',
    'sending' => 'Sending...',
    'remind' => 'Remind',
];
