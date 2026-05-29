<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: caretaker home dashboard. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Caretaker Dashboard',
    'header' => [
        'property_fallback' => 'Property',
        'property_operations' => '{name} Operations',
        'buildings_assigned' => '{count} Building(s) Assigned',
        'record_readings' => 'Record Readings',
    ],
    'action_items' => [
        'urgent_tickets_title' => 'Urgent Tickets',
        'urgent_tickets_description' => 'Require immediate attention',
        'no_urgent_title' => 'No Urgent Issues',
        'no_urgent_description' => 'All urgent tickets resolved',
        'open_tickets_title' => 'Open Tickets',
        'open_tickets_description' => 'Awaiting resolution',
        'no_open_title' => 'No Open Tickets',
        'no_open_description' => 'All tickets resolved',
        'pending_readings_title' => 'Pending Readings',
        'pending_readings_description' => 'Awaiting input',
        'total_units_title' => 'Total Units',
        'total_units_subtitle' => '{count} occupied',
        'action_view' => 'View',
        'action_view_all' => 'View All',
        'action_input' => 'Input',
    ],
    'tasks' => [
        'heading' => "Today's Tasks",
        'subtitle' => 'Priority sorted tickets assigned to you',
        'view_all' => 'View All',
        'empty_title' => 'All caught up!',
        'empty_subtitle' => 'No tasks assigned to you',
        'unit_label' => 'Unit {number} •',
    ],
    'quick_actions' => [
        'heading' => 'Quick Actions',
        'input_readings_title' => 'Input Water Readings',
        'input_readings_subtitle' => 'Record monthly meter readings',
        'view_tickets_title' => 'View My Tickets',
        'view_tickets_subtitle' => '{count} open tickets',
        'report_issue_title' => 'Report New Issue',
        'report_issue_subtitle' => 'Create a maintenance ticket',
    ],
    'unit_status' => [
        'heading' => 'Unit Status Overview',
        'occupied' => 'Occupied',
        'vacant' => 'Vacant',
        'maintenance' => 'Maintenance',
        'total_units' => 'Total Units',
    ],
    'ticket_summary' => [
        'heading' => 'My Ticket Summary',
        'resolved' => 'Resolved',
        'open' => 'Open',
        'total_assigned' => 'Total Assigned',
    ],
    'landlord_contact' => [
        'heading' => 'Landlord Contact',
    ],
];
