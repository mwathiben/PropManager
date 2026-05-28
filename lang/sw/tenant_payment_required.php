<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration (Swahili): tenant initial-payment gating page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Malipo Yanahitajika',
    'header_title' => 'Malipo ya Awali Yanahitajika',
    'header_subtitle' => 'Kamilisha malipo yako ili kupata huduma za mpangaji',
    'status' => [
        'pending_payment_title' => 'Malipo Yanahitajika',
        'pending_payment_message' => 'Tafadhali pakia uthibitisho wa malipo au lipa mtandaoni ili kuendelea.',
        'verification_pending_title' => 'Uhakiki Unasubiri',
        'verification_pending_message' => 'Uthibitisho wako wa malipo umewasilishwa na unasubiri uhakiki kutoka kwa mwenye nyumba.',
        'rejected_title' => 'Uhakiki Umekataliwa',
        'rejected_default_message' => 'Uthibitisho wako wa malipo umekataliwa. Tafadhali wasilisha tena.',
        'verified_title' => 'Malipo Yamethibitishwa',
        'verified_message' => 'Malipo yako yamethibitishwa.',
    ],
    'unit_card' => [
        'heading' => 'Chumba Chako',
        'building_label' => 'Jengo',
        'unit_label' => 'Chumba',
    ],
    'breakdown' => [
        'heading' => 'Malipo Yanahitajika',
        'security_deposit' => 'Dhamana',
        'first_month_rent' => 'Kodi ya Mwezi wa Kwanza',
        'other_charges_default' => 'Malipo Mengine',
        'total_required' => 'Jumla Inayohitajika',
        'amount_paid' => 'Kiasi Kilicholipwa',
        'balance_due' => 'Salio Linalodaiwa',
    ],
    'pay_online' => [
        'heading' => 'Lipa Mtandaoni',
        'description' => 'Lipa kwa usalama kwa kadi yako au pesa za simu. Malipo yako yatathibitishwa kiotomatiki.',
        'cta' => 'Lipa {amount} Sasa',
    ],
    'divider_or_upload' => 'au pakia uthibitisho wa malipo',
    'upload' => [
        'heading' => 'Pakia Uthibitisho wa Malipo',
        'description' => 'Ikiwa tayari umetuma fedha kupitia benki au pesa za simu, pakia uthibitisho wako hapa.',
        'click_to_upload' => 'Bofya kupakia',
        'click_to_upload_suffix' => 'au buruta na uweke',
        'file_constraints' => 'PDF, JPG, PNG hadi 10MB kila moja',
        'submit_idle' => 'Wasilisha kwa Uhakiki',
        'submit_processing' => 'Inapakia...',
        'errors' => [
            'invalid_type' => 'Faili za PDF, JPG na PNG pekee ndizo zinazoruhusiwa.',
            'too_large' => 'Kila faili haipaswi kuzidi 10MB.',
        ],
    ],
    'submitted' => [
        'heading' => 'Hati Zilizowasilishwa',
    ],
    'help' => [
        'heading' => 'Unahitaji msaada?',
        'body' => 'Ikiwa una maswali kuhusu malipo yako au unahitaji usaidizi, tafadhali wasiliana na meneja wa mali yako.',
    ],
];
