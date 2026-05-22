<?php

declare(strict_types=1);

/**
 * Phase-79 WATER-HUB lang namespace. Parity: mirror en / sw / ar exactly.
 */

return [
    'module_disabled' => 'Water billing is not enabled. Configure water charges in a building\'s water settings to use the water hub.',
    'hub' => [
        'title' => 'Water hub',
        'subtitle_caretaker' => 'Record meter readings for your buildings',
        'subtitle_landlord' => 'Review and approve water readings',
    ],
    'tabs' => [
        'record' => 'Record readings',
        'review' => 'Review',
        'history' => 'History',
        'settings' => 'Settings',
    ],
    'tenant' => [
        'title' => 'My water',
        'subtitle' => 'Your meter readings and water charges',
        'empty' => 'No water readings recorded for your unit yet.',
        'date' => 'Date',
        'consumption' => 'Consumption',
        'cost' => 'Charge',
        'status' => 'Status',
    ],
    'settings' => [
        'title' => 'Water Settings',
        'subtitle' => 'Configure water billing rates and methods',
        'global_title' => 'Global Water Billing Settings',
        'global_hint' => 'These settings apply to all buildings unless overridden at the building level.',
        'billing_method' => 'Billing Method',
        'type_consumption' => 'Per Unit Consumption',
        'type_consumption_hint' => 'Charge based on meter readings',
        'type_flat' => 'Flat Rate',
        'type_flat_hint' => 'Fixed monthly charge',
        'type_none' => 'No Water Billing',
        'type_none_hint' => 'Water included in rent',
        'type_inherit' => 'Use Global Settings',
        'rate_per_unit' => 'Rate per Unit',
        'rate_per_unit_hint' => 'Amount charged per unit of water consumed',
        'flat_rate' => 'Monthly Flat Rate',
        'flat_rate_hint' => 'Fixed amount charged monthly to each tenant',
        'building_title' => 'Building-Specific Settings',
        'building_hint' => 'Override global settings for specific buildings. Set to "Use Global Settings" to inherit from above.',
        'units' => 'units',
        'no_buildings_title' => 'No buildings found',
        'no_buildings_hint' => 'Add buildings to configure per-building water settings.',
        'saving' => 'Saving...',
        'save' => 'Save Settings',
    ],
];
