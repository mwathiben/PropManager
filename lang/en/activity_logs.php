<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: activity/audit-log list page. Mirror en/sw/ar.
 */
return [
    'title' => 'Activity Logs',
    'subtitle' => 'Track all tenant and property activities',
    'stats' => [
        'total' => 'Total Activities',
        'today' => 'Today',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
    ],
    'filters' => [
        'search' => 'Search',
        'search_placeholder' => 'Search by description...',
        'activity_type' => 'Activity Type',
        'all_types' => 'All Types',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'clear' => 'Clear',
    ],
    'by_prefix' => 'By:',
    'empty' => [
        'title' => 'No activity logs found',
        'body' => 'Activities will appear here as they occur',
    ],
];
