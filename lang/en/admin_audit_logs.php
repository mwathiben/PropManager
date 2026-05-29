<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: admin (super-admin) audit-log viewer page.
 * Distinct from the landlord-facing `activity_logs` namespace.
 * Mirror en/sw/ar.
 */
return [
    'title' => 'Audit Logs',
    'export_csv' => 'Export CSV',
    'filters' => [
        'search' => 'Search',
        'search_placeholder' => 'Search...',
        'event_type' => 'Event Type',
        'all_events' => 'All Events',
        'model_type' => 'Model Type',
        'all_models' => 'All Models',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'clear' => 'Clear Filters',
        'apply' => 'Apply Filters',
    ],
    'columns' => [
        'datetime' => 'Date/Time',
        'user' => 'User',
        'event' => 'Event',
        'model' => 'Model',
        'changes' => 'Changes',
        'ip' => 'IP',
        'actions' => 'Actions',
    ],
    'system_user' => 'System',
    'view_details' => 'View Details',
    'empty' => [
        'title' => 'No audit logs found',
        'body' => 'Adjust your filters above. Audit logs are generated automatically as users act on records.',
    ],
    'detail' => [
        'heading' => 'Audit Log #{id}',
    ],
];
