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
];
