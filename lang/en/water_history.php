<?php

declare(strict_types=1);

return [
    'filters' => [
        'all_buildings' => 'All Buildings',
        'all_status' => 'All Status',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'invoiced' => 'Invoiced',
        'clear' => 'Clear filters',
    ],
    'table' => [
        'unit' => 'Unit',
        'reading' => 'Reading',
        'date' => 'Date',
        'status' => 'Status',
    ],
    'unit_prefix' => 'Unit {number}',
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'invoiced' => 'Invoiced',
    ],
    'empty' => [
        'title' => 'No readings found',
        'description_filtered' => 'Try adjusting your filters.',
        'description_default' => 'Meter readings will appear here once recorded.',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} results',
    ],
];
