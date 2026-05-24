<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finance hub reports tab. Mirror en/sw/ar.
 */
return [
    'heading' => 'Financial Reports',
    'subheading' => 'Analyze revenue, expenses, and collection performance',
    'filters_button' => 'Filters',
    'export_formats' => [
        'xlsx' => 'Excel (.xlsx)',
        'pdf' => 'PDF',
        'csv' => 'CSV',
    ],
    'export_buttons' => [
        'rent_roll' => 'Rent Roll',
        'property_pnl' => 'Property P&L',
    ],
    'periods' => [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'last_quarter' => 'Last Quarter',
        'ytd' => 'Year to Date',
        'this_fy' => 'This Fiscal Year',
        'last_fy' => 'Last Fiscal Year',
        '12' => 'Last 12 Months',
        '6' => 'Last 6 Months',
        '3' => 'Last 3 Months',
        'custom' => 'Custom Range',
    ],
    'tools' => [
        'builder' => [
            'name' => 'Report Builder',
            'desc' => 'Compose custom reports from your data',
        ],
        'dashboards' => [
            'name' => 'Dashboards',
            'desc' => 'Build dashboards from saved reports + metrics',
        ],
        'templates' => [
            'name' => 'Templates',
            'desc' => 'Clone a curated report to get started',
        ],
        'scheduled' => [
            'name' => 'Scheduled',
            'desc' => 'Email reports on a recurring cadence',
        ],
        'shares' => [
            'name' => 'Shared Links',
            'desc' => 'Read-only links to a saved report',
        ],
        'metrics' => [
            'name' => 'Custom Metrics',
            'desc' => 'Author formulas as derived columns',
        ],
    ],
    'filters' => [
        'building' => 'Building',
        'all_buildings' => 'All Buildings',
        'from' => 'From',
        'to' => 'To',
        'compare' => 'Compare to previous period',
        'apply' => 'Apply',
        'clear' => 'Clear',
    ],
    'metrics' => [
        'total_invoiced' => 'Total Invoiced',
        'total_collected' => 'Total Collected',
        'total_expenses' => 'Total Expenses',
        'avg_collection_rate' => 'Avg Collection Rate',
    ],
    'revenue' => [
        'title' => 'Revenue vs Expenses',
        'net' => 'Net: {amount}',
        'invoiced' => 'Invoiced',
        'collected' => 'Collected',
        'expenses' => 'Expenses',
        'invoiced_tooltip' => 'Invoiced: {amount}',
        'collected_tooltip' => 'Collected: {amount}',
        'expenses_tooltip' => 'Expenses: {amount}',
        'empty_title' => 'No revenue data',
        'empty_body' => 'Try adjusting your filters or date range',
    ],
    'collection' => [
        'title' => 'Collection Rate Trend',
        'target' => '85% Target',
        'empty_title' => 'No collection data',
        'empty_body' => 'Data will appear when invoices are created',
    ],
    'occupancy' => [
        'title' => 'Occupancy by Building',
        'building' => 'Building',
        'units' => 'Units',
        'occupied' => 'Occupied',
        'vacant' => 'Vacant',
        'rate' => 'Rate',
        'total' => 'Total',
        'empty_title' => 'No buildings found',
        'empty_body' => 'Add properties to see occupancy data',
    ],
    'arrears' => [
        'title' => 'Arrears Aging',
        'total_outstanding' => 'Total Outstanding',
        'buckets' => [
            'current' => 'Current',
            '1-30' => '1-30 Days',
            '31-60' => '31-60 Days',
            '61-90' => '61-90 Days',
            '90+' => '90+ Days',
        ],
        'empty_title' => 'No Outstanding Arrears',
        'empty_body' => 'All invoices are paid on time',
    ],
    'expenses_by_category' => [
        'title' => 'Expenses by Category',
        'total' => 'Total',
        'expense_count' => '{count} expense | {count} expenses',
        'empty_title' => 'No expenses recorded',
        'empty_body' => 'Expenses will appear here when added',
    ],
    'water' => [
        'title' => 'Water Consumption',
        'units' => 'units',
        'total_cost' => '{amount} total cost',
        'top_consumers' => 'Top Consumers',
        'consumer_units' => '{count} units',
        'empty' => 'No water consumption data',
    ],
    'top_units' => [
        'title' => 'Top Performing Units',
        'on_time' => '{onTime}/{total} on-time',
        'empty_title' => 'No performance data',
        'empty_body' => 'Data appears when invoices are generated',
    ],
];
