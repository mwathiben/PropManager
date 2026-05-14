<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages (Kiswahili)
    |--------------------------------------------------------------------------
    |
    | Phase-24 I18N-SWAHILI-1: Kiswahili translation of
    | lang/en/validation.php. Key-for-key parity with the English
    | source. Laravel's built-in rule messages (required/email/...) are
    | covered by the published vendor sw translations; this file holds
    | the app's custom rule + attribute strings.
    |
    */

    'custom' => [
        'month' => [
            'required' => 'Mwezi wa malipo unahitajika.',
            'integer' => 'Mwezi lazima uwe nambari.',
            'min' => 'Mwezi lazima uwe kati ya 1 na 12.',
            'max' => 'Mwezi lazima uwe kati ya 1 na 12.',
        ],
        'year' => [
            'required' => 'Mwaka wa malipo unahitajika.',
            'integer' => 'Mwaka lazima uwe nambari.',
            'min' => 'Mwaka lazima uwe 2020 au baadaye.',
            'max' => 'Mwaka hauwezi kuzidi 2100.',
        ],
        'email' => [
            'unique' => 'Mtumiaji mwenye barua pepe hii tayari yupo.',
        ],
        'rent_amount' => [
            'min' => 'Kiasi cha kodi hakiwezi kuwa hasi.',
        ],
        'deposit_amount' => [
            'min' => 'Kiasi cha amana hakiwezi kuwa hasi.',
        ],
        'service_charge' => [
            'min' => 'Ada ya huduma haiwezi kuwa hasi.',
        ],
        'amount' => [
            'min' => 'Kiasi hakiwezi kuwa hasi.',
            'required' => 'Kiasi kinahitajika.',
        ],
        'payment_method' => [
            'required' => 'Njia ya malipo inahitajika.',
            'in' => 'Njia ya malipo iliyochaguliwa si sahihi.',
        ],
        'phone' => [
            'required' => 'Nambari ya simu inahitajika.',
        ],
        'start_date' => [
            'required' => 'Tarehe ya kuanza inahitajika.',
            'date' => 'Tarehe ya kuanza lazima iwe tarehe halali.',
        ],
        'end_date' => [
            'after' => 'Tarehe ya mwisho lazima iwe baada ya tarehe ya kuanza.',
        ],
        'meter_reading' => [
            'min' => 'Usomaji wa mita hauwezi kuwa hasi.',
            'gte' => 'Usomaji wa sasa lazima uwe sawa au zaidi ya usomaji uliopita.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes (Kiswahili)
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'rent_amount' => 'kiasi cha kodi',
        'deposit_amount' => 'kiasi cha amana',
        'service_charge' => 'ada ya huduma',
        'start_date' => 'tarehe ya kuanza',
        'end_date' => 'tarehe ya mwisho',
        'id_number' => 'nambari ya kitambulisho',
        'meter_reading' => 'usomaji wa mita',
        'billing_period' => 'kipindi cha malipo',
    ],

];
