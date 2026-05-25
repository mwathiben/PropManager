<?php

declare(strict_types=1);

return [
    'select_leases' => 'Select Leases',
    'select_all' => 'Select All',
    'deselect_all' => 'Deselect All',
    'no_units' => 'No units with active leases found',
    'unknown_tenant' => 'Unknown Tenant',
    'selected_count' => '{count} lease selected | {count} leases selected',
    'extend' => [
        'title' => 'Extend Leases',
        'period_label' => 'Extension Period',
        'months_1' => '1 Month',
        'months_3' => '3 Months',
        'months_6' => '6 Months',
        'months_12' => '12 Months',
        'months_24' => '24 Months',
        'processing' => 'Processing...',
        'submit' => 'Extend Leases',
    ],
    'terminate' => [
        'title' => 'Terminate Leases',
        'date_label' => 'Termination Date',
        'reason_label' => 'Reason',
        'reason_placeholder' => 'Optional',
        'mark_vacant' => 'Mark units as vacant',
        'processing' => 'Processing...',
        'submit' => 'Terminate Leases',
    ],
    'deposit' => [
        'title' => 'Adjust Deposits',
        'type_label' => 'Type',
        'type_percentage' => '%',
        'type_fixed' => '+/-',
        'type_set' => 'Set',
        'value_label' => 'Value',
        'processing' => 'Processing...',
        'submit' => 'Adjust Deposits',
    ],
    'notify_tenants' => 'Notify tenants',
    'alert' => [
        'select_at_least_one' => 'Please select at least one lease',
    ],
    'confirm' => [
        'terminate' => 'Are you sure you want to terminate {count} lease(s)?',
    ],
];
