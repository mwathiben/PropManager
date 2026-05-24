<?php

declare(strict_types=1);

/**
 * Phase-105 PORTFOLIO-HOME: the landlord landing (cross-property overview). Mirror en/sw/ar.
 */
return [
    'title' => 'Portfolio',
    'subtitle' => 'All your properties at a glance',
    'kpi' => [
        'occupancy' => 'Occupancy',
        'rent_roll' => 'Monthly rent roll',
        'arrears' => 'Outstanding arrears',
        'units' => 'Units',
        'properties' => 'Properties',
        'buildings' => 'Buildings',
        'units_subtitle' => ':occupied of :total occupied',
        'properties_subtitle' => ':buildings buildings',
    ],
    'actions' => [
        'title' => 'Needs attention',
        'overdue_invoices' => 'Overdue invoices',
        'open_tickets' => 'Open tickets',
        'expiring_leases' => 'Leases expiring (60 days)',
        'none' => 'Nothing needs attention right now.',
    ],
    'properties_heading' => 'Your properties',
    'card' => [
        'occupancy' => 'Occupancy',
        'rent_roll' => 'Rent roll',
        'arrears' => 'Arrears',
        'units' => ':occupied/:total units',
        'open' => 'Open dashboard',
    ],
    'none' => 'No properties yet. Add your first property to get started.',
];
