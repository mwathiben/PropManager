<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: bulk-operations hub landing/tab shell. Mirror en/sw/ar.
 */
return [
    'title' => 'Bulk Operations',
    'subtitle' => 'Perform batch updates on units, leases, and rent',
    'tabs' => [
        'rent' => 'Rent Adjustment',
        'status' => 'Unit Status',
        'lease' => 'Lease Management',
        'target' => 'Target Rent',
    ],
    'filters' => [
        'heading' => 'Filter Selection',
        'building_wing' => 'Building / Wing',
        'all_buildings' => 'All Buildings',
        'all_wings' => 'All Wings',
        'property' => 'Property',
        'all_properties' => 'All Properties',
        'status' => 'Status',
        'all_statuses' => 'All Statuses',
        'found_label' => 'Found:',
        'units_suffix' => 'units,',
        'with_active_leases' => 'with active leases',
        'strict_mode' => 'Strict Mode: Operations limited to selected building/wing',
    ],
    'status' => [
        'vacant' => 'Vacant',
        'occupied' => 'Occupied',
        'maintenance' => 'Maintenance',
        'arrears' => 'Arrears',
    ],
];
