<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: super-admin platform billing settings page. Mirror en/sw/ar.
 */
return [
    'title' => 'Mipangilio ya Bili ya Jukwaa',
    'stats' => [
        'monthly_revenue' => 'Mapato ya Mwezi Huu',
        'transactions' => 'Miamala',
        'avg_fee_percent' => 'Wastani wa Ada %',
        'total_processed' => 'Jumla Iliyochakatwa',
    ],
    'tabs' => [
        'settings' => 'Mipangilio',
        'history' => 'Historia ya Mabadiliko',
    ],
    'billing_model' => [
        'heading' => 'Muundo wa Bili',
        'current_label' => 'Sasa:',
        'select_label' => 'Chagua Muundo',
        'reason_label' => 'Sababu (si lazima)',
        'reason_placeholder' => 'Kwa nini unabadilisha muundo wa bili?',
        'submit' => 'Sasisha Muundo wa Bili',
        'submitting' => 'Inasasisha...',
    ],
    'calculator' => [
        'heading' => 'Kikokotoo cha Ada',
        'amount_placeholder' => 'Weka kiasi',
        'calculate' => 'Kokotoa',
        'gross_amount' => 'Kiasi Cha Jumla:',
        'platform_fee' => 'Ada ya Jukwaa ({percent}%):',
        'landlord_receives' => 'Mwenye Nyumba Anapata:',
    ],
    'fees' => [
        'heading' => 'Mipangilio ya Ada',
        'transaction_fee_percent' => 'Ada ya Muamala %',
        'transaction_fee_hint' => 'Asilimia inayotozwa kwa kila muamala',
        'minimum_fee' => 'Ada ya Chini Kabisa ({currency})',
        'maximum_fee' => 'Ada ya Juu Kabisa ({currency})',
        'maximum_fee_placeholder' => 'Hakuna kikomo',
        'fee_bearer' => 'Mbebaji wa Ada',
        'hybrid_discount' => 'Punguzo la Mteja wa Mchanganyiko %',
        'hybrid_discount_hint' => 'Punguzo la ada kwa wateja waliojisajili (100 = hakuna ada)',
        'reason_label' => 'Sababu (si lazima)',
        'reason_placeholder' => 'Kwa nini unabadilisha ada?',
        'submit' => 'Hifadhi Mipangilio ya Ada',
        'submitting' => 'Inahifadhi...',
    ],
    'history' => [
        'heading' => 'Mabadiliko ya Hivi Karibuni',
        'reason_prefix' => 'Sababu: {reason}',
        'empty' => 'Hakuna mabadiliko yaliyorekodiwa bado',
    ],
];
