<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub inbox tab. Mirror en/sw/ar.
 */
return [
    'title' => 'Inbox',
    'subtitle' => 'Tenant messages from WhatsApp and SMS',
    'unread_count' => '({count} unread)',
    'mark_all_read' => 'Mark All Read',
    'full_view' => 'Full View',
    'search_placeholder' => 'Search messages...',
    'filter' => [
        'all' => 'All',
        'unread' => 'Unread',
        'read' => 'Read',
        'status' => 'Filter by status',
    ],
    'ticket' => 'Ticket #{id}',
    'empty' => [
        'title' => 'No messages',
        'description' => 'Tenant messages from WhatsApp and SMS will appear here.',
    ],
    'showing' => 'Showing {from} - {to} of {total}',
    'previous' => 'Previous',
    'next' => 'Next',
    'status' => [
        'received' => 'Unread',
        'processed' => 'Read',
        'action_taken' => 'Actioned',
        'ignored' => 'Ignored',
    ],
    'confirm' => [
        'mark_all_read' => 'Mark all messages as read?',
    ],
];
