<?php

declare(strict_types=1);

return [
    'title' => 'Meter Readings',
    'subtitle' => 'Latest reading per metered unit',
    'record' => 'Record Readings',
    'table' => [
        'unit' => 'Unit',
        'last_reading' => 'Last Reading',
        'date' => 'Date',
    ],
    'unit_prefix' => 'Unit {number}',
    'no_reading_yet' => 'No reading yet',
    'no_metered_units_in_building' => 'No metered units in this building.',
    'empty' => [
        'title' => 'No metered units',
        'description' => 'Enable water meters on units to start tracking consumption.',
    ],
];
