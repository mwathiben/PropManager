<?php

declare(strict_types=1);

return [
    'custom' => [
        'month' => [
            'required' => '[TODO-ar] Billing month is required.',
            'integer' => '[TODO-ar] Month must be a number.',
            'min' => '[TODO-ar] Month must be between 1 and 12.',
            'max' => '[TODO-ar] Month must be between 1 and 12.',
        ],
        'year' => [
            'required' => '[TODO-ar] Billing year is required.',
            'integer' => '[TODO-ar] Year must be a number.',
            'min' => '[TODO-ar] Year must be 2020 or later.',
            'max' => '[TODO-ar] Year cannot exceed 2100.',
        ],
        'email' => [
            'unique' => '[TODO-ar] A user with this email already exists.',
        ],
        'rent_amount' => [
            'min' => '[TODO-ar] Rent amount cannot be negative.',
        ],
        'deposit_amount' => [
            'min' => '[TODO-ar] Deposit amount cannot be negative.',
        ],
        'service_charge' => [
            'min' => '[TODO-ar] Service charge cannot be negative.',
        ],
        'amount' => [
            'min' => '[TODO-ar] Amount cannot be negative.',
            'required' => '[TODO-ar] Amount is required.',
        ],
        'payment_method' => [
            'required' => '[TODO-ar] Payment method is required.',
            'in' => '[TODO-ar] Invalid payment method selected.',
        ],
        'phone' => [
            'required' => '[TODO-ar] Phone number is required.',
        ],
        'start_date' => [
            'required' => '[TODO-ar] Start date is required.',
            'date' => '[TODO-ar] Start date must be a valid date.',
        ],
        'end_date' => [
            'after' => '[TODO-ar] End date must be after start date.',
        ],
        'meter_reading' => [
            'min' => '[TODO-ar] Meter reading cannot be negative.',
            'gte' => '[TODO-ar] Current reading must be greater than or equal to previous reading.',
        ],
    ],
    'attributes' => [
        'rent_amount' => '[TODO-ar] rent amount',
        'deposit_amount' => '[TODO-ar] deposit amount',
        'service_charge' => '[TODO-ar] service charge',
        'start_date' => '[TODO-ar] start date',
        'end_date' => '[TODO-ar] end date',
        'id_number' => '[TODO-ar] ID number',
        'meter_reading' => '[TODO-ar] meter reading',
        'billing_period' => '[TODO-ar] billing period',
    ],
];
