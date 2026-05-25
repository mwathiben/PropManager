<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: deposit forfeit modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Deposit Forfeited',
    'success_body' => 'The deposit has been forfeited.',
    'heading' => 'Forfeit Deposit',
    'warning' => 'This action will forfeit the entire deposit. This cannot be undone.',
    'deposit_amount' => 'Deposit Amount',
    'tenant' => 'Tenant',
    'unit' => 'Unit',
    'reason_label' => 'Reason for Forfeiture',
    'select_reason' => 'Select a reason',
    'cancel' => 'Cancel',
    'forfeit_deposit' => 'Forfeit Deposit',
    'processing' => 'Processing...',
    'reasons' => [
        'rent_arrears' => 'Outstanding rent arrears',
        'property_damage' => 'Severe property damage',
        'lease_violation' => 'Lease violation',
        'abandonment' => 'Abandonment',
        'illegal_activity' => 'Illegal activity',
        'other' => 'Other',
    ],
    'errors' => [
        'reason_required' => 'Please provide a reason for forfeiting the deposit',
    ],
];
