<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages
    |--------------------------------------------------------------------------
    |
    | Custom validation messages for application-specific validation rules.
    | Extends Laravel's default validation.php with domain-specific messages.
    |
    */

    'custom' => [
        'month' => [
            'required' => 'Billing month is required.',
            'integer' => 'Month must be a number.',
            'min' => 'Month must be between 1 and 12.',
            'max' => 'Month must be between 1 and 12.',
        ],
        'year' => [
            'required' => 'Billing year is required.',
            'integer' => 'Year must be a number.',
            'min' => 'Year must be 2020 or later.',
            'max' => 'Year cannot exceed 2100.',
        ],
        'email' => [
            'unique' => 'A user with this email already exists.',
        ],
        'rent_amount' => [
            'min' => 'Rent amount cannot be negative.',
        ],
        'deposit_amount' => [
            'min' => 'Deposit amount cannot be negative.',
        ],
        'service_charge' => [
            'min' => 'Service charge cannot be negative.',
        ],
        'amount' => [
            'min' => 'Amount cannot be negative.',
            'required' => 'Amount is required.',
        ],
        'payment_method' => [
            'required' => 'Payment method is required.',
            'in' => 'Invalid payment method selected.',
        ],
        'phone' => [
            'required' => 'Phone number is required.',
        ],
        'start_date' => [
            'required' => 'Start date is required.',
            'date' => 'Start date must be a valid date.',
        ],
        'end_date' => [
            'after' => 'End date must be after start date.',
        ],
        'meter_reading' => [
            'min' => 'Meter reading cannot be negative.',
            'gte' => 'Current reading must be greater than or equal to previous reading.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute placeholders
    | with something more reader friendly.
    |
    */

    'attributes' => [
        'rent_amount' => 'rent amount',
        'deposit_amount' => 'deposit amount',
        'service_charge' => 'service charge',
        'start_date' => 'start date',
        'end_date' => 'end date',
        'id_number' => 'ID number',
        'meter_reading' => 'meter reading',
        'billing_period' => 'billing period',
    ],

];
