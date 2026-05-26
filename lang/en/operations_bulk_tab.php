<?php

declare(strict_types=1);

/**
 * i18n migration: operations hub bulk tab. Mirror en/sw/ar.
 */
return [
    'stats' => [
        'total_units' => 'Total Units',
        'occupied' => 'Occupied',
        'active_leases' => 'Active Leases',
        'buildings' => 'Buildings',
    ],
    'operations' => [
        'rent_adjustment' => [
            'name' => 'Rent Adjustment',
            'description' => 'Increase or decrease rent for multiple units at once',
        ],
        'unit_status' => [
            'name' => 'Unit Status Update',
            'description' => 'Update status for multiple units (vacant, maintenance, etc.)',
        ],
        'lease_management' => [
            'name' => 'Lease Management',
            'description' => 'Extend or terminate multiple leases at once',
        ],
        'target_rent' => [
            'name' => 'Target Rent Update',
            'description' => 'Update market rent values for multiple units',
        ],
    ],
    'quick_select' => [
        'heading' => 'Quick Select by Building',
        'empty' => 'No buildings available.',
    ],
];
