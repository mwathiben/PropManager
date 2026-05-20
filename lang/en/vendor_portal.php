<?php

return [
    // Middleware / flash (server-rendered).
    'link_required' => 'Your portal link is invalid or has expired. Ask the landlord to send a new one.',
    'logged_out' => 'You have left the vendor portal.',
    'no_email' => 'This vendor has no email address on file.',
    'link_sent' => 'A fresh portal link has been emailed to the vendor.',

    // Magic-link email (server-rendered, Laravel :colon placeholders).
    'email' => [
        'subject' => 'Your contractor portal link',
        'heading' => 'Your contractor portal',
        'greeting' => 'Hi :name,',
        'body' => 'Use the secure link below to view jobs assigned to you, accept or decline them, log time, and see your statement.',
        'cta' => 'Open the portal',
        'expiry' => 'This link is valid for 7 days. If it expires, ask the landlord to send a new one.',
        'signoff' => '— :app',
    ],

    // Portal UI (client-rendered, vue-i18n {curly} placeholders).
    'nav' => [
        'dashboard' => 'Dashboard',
        'inbox' => 'Jobs',
        'statement' => 'Statement',
        'sla' => 'Performance',
        'logout' => 'Leave portal',
    ],
    'dashboard' => [
        'title' => 'Welcome, {name}',
        'pending' => 'Pending assignments',
        'open' => 'Open jobs',
        'overdue' => 'Overdue',
    ],
    'inbox' => [
        'title' => 'Your jobs',
        'pending' => 'Awaiting your response',
        'active' => 'Active jobs',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'decline_reason' => 'Reason (optional)',
        'accepted' => 'Job accepted.',
        'declined' => 'Job declined — the landlord has been notified.',
        'already_responded' => 'You have already responded to this job.',
        'empty' => 'No jobs assigned to you yet.',
        'due' => 'Due',
    ],

    // Landlord notification (server-rendered, :colon).
    'declined_email' => [
        'subject' => 'A vendor declined ticket: :ticket',
        'heading' => 'Vendor declined a job',
        'body' => ':vendor declined the ticket ":ticket". It is back in your queue to reassign.',
        'reason_label' => 'Reason given',
        'cta' => 'Reassign it from the ticket page.',
        'signoff' => '— :app',
    ],

    'job' => [
        'title' => 'Job detail',
        'description' => 'Description',
        'log_time' => 'Log time',
        'minutes' => 'Minutes',
        'note' => 'Note (optional)',
        'add_time' => 'Add time',
        'total_time' => 'Total time logged',
        'prior_logs' => 'Time entries',
        'resolve' => 'Mark resolved',
        'resolve_notes' => 'Resolution notes',
        'mark_resolved' => 'Mark resolved',
        'time_logged' => 'Time logged.',
        'resolved' => 'Job marked resolved.',
        'not_accepted' => 'Accept the job before logging time or resolving it.',
        'not_open' => 'This job is no longer open.',
        'minutes_unit' => 'min',
    ],

    'statement' => [
        'title' => 'Statement',
        'period' => 'Period',
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Apply',
        'ticket_costs' => 'Ticket costs',
        'expenses' => 'Expenses',
        'total' => 'Total',
        'export' => 'Export CSV',
        'empty' => 'No recorded costs in this period.',
        'amount' => 'Amount',
        'date' => 'Date',
        'reference' => 'Reference',
    ],
];
