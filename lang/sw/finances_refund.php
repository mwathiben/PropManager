<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: payment refund modal. Mirror en/sw/ar.
 */
return [
    'success_title' => 'Marejesho Yameanzishwa!',
    'success_body' => 'Ombi la marejesho limewasilishwa.',
    'heading' => 'Anzisha Marejesho',
    'notice' => 'Marejesho yanaweza kuchukua siku 3-5 za kazi kushughulikiwa kulingana na njia ya malipo.',
    'payment_label' => 'Malipo',
    'select_payment' => 'Chagua malipo',
    'already_refunded' => ' (Tayari Yamerejeshwa)',
    'original_amount' => 'Kiasi cha Awali',
    'payment_method' => 'Njia ya Malipo',
    'refund_amount' => 'Kiasi cha Marejesho',
    'full_amount' => 'Kiasi Kamili',
    'reason_label' => 'Sababu',
    'select_reason' => 'Chagua sababu',
    'refund_method' => 'Njia ya Marejesho',
    'cancel' => 'Ghairi',
    'processing' => 'Inashughulikiwa...',
    'methods' => [
        'original' => 'Njia ya Malipo ya Awali',
        'cash' => 'Fedha Taslimu',
        'bank_transfer' => 'Uhamisho wa Benki',
        'mobile_money' => 'M-Pesa',
    ],
    'reasons' => [
        'overpayment' => 'Malipo ya Ziada',
        'duplicate' => 'Malipo ya Marudio',
        'moved_out' => 'Mpangaji Amehama',
        'billing_error' => 'Hitilafu ya Ankara',
        'service_not_provided' => 'Huduma Haikutolewa',
        'other' => 'Nyingine',
    ],
    'errors' => [
        'select_payment' => 'Tafadhali chagua malipo',
        'valid_amount' => 'Tafadhali weka kiasi sahihi',
        'amount_exceeds' => 'Kiasi hakiwezi kuzidi {max}',
        'select_reason' => 'Tafadhali chagua sababu',
        'select_method' => 'Tafadhali chagua njia ya marejesho',
        'failed' => 'Imeshindwa kuunda marejesho',
    ],
];
