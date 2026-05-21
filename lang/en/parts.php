<?php

declare(strict_types=1);

/**
 * Phase-75 parts lang namespace. Houses the landlord-facing parts pricing
 * surface (price-history trend + supplier comparison).
 *
 * Parity contract: keys MUST mirror across en / sw / ar exactly in order +
 * nesting (Phase 24 CI watchdog does identity comparison on array_keys).
 */

return [
    'pricing' => [
        'title' => 'Parts pricing',
        'subtitle' => 'Track cost trends and compare suppliers per part',
        'empty' => 'No active parts yet.',
        'current_cost' => 'Current cost',
        'in_stock' => 'In stock',
        'history_title' => 'Cost history',
        'history_empty' => 'No cost changes recorded yet.',
        'suppliers_title' => 'Suppliers',
        'suppliers_empty' => 'No suppliers added yet.',
        'col_supplier' => 'Supplier',
        'col_unit_cost' => 'Unit cost',
        'col_lead_time' => 'Lead time',
        'col_min_order' => 'Min order',
        'days' => '{count} days',
        'cheapest' => 'Cheapest',
        'fastest' => 'Fastest',
        'add_supplier' => 'Add supplier',
        'select_vendor' => 'Select vendor',
        'unit_cost_label' => 'Unit cost',
        'lead_time_label' => 'Lead time (days)',
        'min_order_label' => 'Min order qty',
        'save' => 'Save supplier',
        'remove' => 'Remove',
        'flash' => [
            'supplier_saved' => 'Supplier saved.',
            'supplier_removed' => 'Supplier removed.',
        ],
        'source' => [
            'manual' => 'Manual',
            'purchase_order' => 'Purchase order',
            'import' => 'Import',
        ],
    ],
];
