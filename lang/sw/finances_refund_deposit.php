<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: deposit refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Amana Imerejeshwa!',
    'success_body' => 'Marejesho ya amana yameshughulikiwa.',
    'heading' => 'Rejesha Amana',
    'deposit_amount' => 'Kiasi cha Amana',
    'tenant' => 'Mpangaji',
    'unit' => 'Kipengele',
    'refund_amount' => 'Kiasi cha Marejesho',
    'full_amount' => 'Kiasi Kamili',
    'deductions' => 'Makato (kama yapo)',
    'reason_label' => 'Sababu ya Makato',
    'select_reason' => 'Chagua sababu',
    'net_refund' => 'Marejesho Halisi kwa Mpangaji',
    'cancel' => 'Ghairi',
    'process_refund' => 'Shughulikia Marejesho',
    'processing' => 'Inashughulikiwa...',
    'reasons' => [
        'unpaid_rent' => 'Kodi ambayo haijalipwa',
        'property_damage' => 'Uharibifu wa mali',
        'cleaning_fees' => 'Ada za usafi',
        'unpaid_utilities' => 'Huduma ambazo hazijalipwa',
        'early_termination' => 'Ada ya kusitisha mapema',
        'other' => 'Nyingine',
    ],
    'errors' => [
        'amount_min' => 'Kiasi cha marejesho lazima kiwe zaidi ya 0',
        'amount_exceeds' => 'Kiasi cha marejesho hakiwezi kuzidi {max}',
        'deductions_negative' => 'Makato hayawezi kuwa hasi',
        'total_exceeds' => 'Kiasi cha marejesho pamoja na makato hakiwezi kuzidi kiasi cha amana',
        'reason_required' => 'Tafadhali toa sababu ya makato',
    ],
];
