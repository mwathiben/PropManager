<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker work-queue (maintenance tickets) page. Mirror en/sw/ar.
 */
return [
    'title' => 'My Tickets',
    'heading' => 'My Work Queue',
    'subtitle' => 'Manage tickets assigned to you',
    'stats' => [
        'urgent' => 'Urgent',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
    ],
    'filter_label' => 'Filter:',
    'all_statuses' => 'All Statuses',
    'active_option' => 'Active (Open/In Progress)',
    'all_priorities' => 'All Priorities',
    'unit_prefix' => '- Unit {number}',
    'reported_by' => 'Reported by {name}',
    'unknown_reporter' => 'Unknown',
    'view' => 'View',
    'acknowledge' => 'Acknowledge',
    'start_work' => 'Start Work',
    'resolve' => 'Resolve',
    'empty' => [
        'title' => 'All caught up!',
        'description' => 'No tickets in your queue matching the current filters.',
    ],
    'pagination' => 'Showing {from} to {to} of {total} tickets',
    'time_ago' => [
        'days' => '{count}d ago',
        'hours' => '{count}h ago',
        'just_now' => 'Just now',
    ],
    'resolve_modal' => [
        'title' => 'Resolve Ticket',
        'notes_label' => 'Resolution Notes',
        'notes_placeholder' => 'Describe what was done to resolve this issue...',
        'resolving' => 'Resolving...',
        'submit' => 'Mark as Resolved',
        'cancel' => 'Cancel',
    ],
];
