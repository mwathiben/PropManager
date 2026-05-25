<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: water-meter reading history page. Mirror en/sw/ar.
 */
return [
    'title' => 'Water Reading History',
    'heading' => 'Water Reading History',
    'add_readings' => 'Add Readings',
    'filters' => [
        'title' => 'Filters',
        'building' => 'Building',
        'all_buildings' => 'All Buildings',
        'unit' => 'Unit',
        'all_units' => 'All Units',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'status' => 'Status',
        'all' => 'All',
        'not_invoiced' => 'Not Invoiced',
        'invoiced' => 'Invoiced',
        'apply' => 'Apply Filters',
        'clear' => 'Clear',
    ],
    'table' => [
        'date' => 'Date',
        'unit' => 'Unit',
        'previous' => 'Previous',
        'current' => 'Current',
        'consumption' => 'Consumption',
        'cost' => 'Cost',
        'status' => 'Status',
        'actions' => 'Actions',
    ],
    'cost_na' => 'N/A',
    'status' => [
        'invoiced' => 'Invoiced',
        'pending' => 'Pending',
    ],
    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'locked' => 'Locked',
    ],
    'pagination' => [
        'showing' => 'Showing {from} to {to} of {total} readings',
    ],
    'empty' => [
        'title' => 'No readings found',
        'description' => 'Try adjusting your filters or add new readings.',
    ],
    'confirm' => [
        'delete' => 'Are you sure you want to delete this reading?',
    ],
];
