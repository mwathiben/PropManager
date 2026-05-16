<?php

declare(strict_types=1);

return [
    'export' => [
        'title' => 'Accounting Export',
        'diagnostics_heading' => 'Mapping diagnostics',
        'run_heading' => 'Run an export',
        'accounts_configured' => ':count active GL accounts configured',
        'invoice_types_unmapped' => ':count invoice types missing a GL mapping',
        'expense_categories_unmapped' => ':count expense categories missing a GL mapping',
        'missing_default_income' => 'No default income account configured',
        'missing_default_expense' => 'No default expense account configured',
        'from' => 'From',
        'to' => 'To',
        'format' => 'Format',
        'download' => 'Download',
    ],
    'period' => [
        'title' => 'Accounting Periods',
        'close_heading' => 'Close a month',
        'month' => 'Month',
        'notes' => 'Notes',
        'close_button' => 'Close month',
        'period' => 'Period',
        'status' => 'Status',
        'closed_at' => 'Closed at',
        'reopen' => 'Reopen',
        'reopen_confirm' => 'Reopen this period? Writes to dates in this window will be allowed again.',
        'closed' => 'Accounting period closed.',
        'reopened' => 'Accounting period reopened.',
    ],
];
