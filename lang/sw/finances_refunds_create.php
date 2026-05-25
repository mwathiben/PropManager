<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: standalone refund-creation page. Mirror en/sw/ar.
 */
return [
    'page_title' => 'Shughulikia Marejesho',
    'back_to_refunds' => 'Rudi kwenye Marejesho',
    'heading' => 'Shughulikia Marejesho',
    'subheading' => 'Unda ombi la marejesho kwa malipo ya mpangaji',
    'success' => [
        'title' => 'Ombi la Marejesho Limeundwa!',
        'body' => 'Marejesho yamewasilishwa kwa kushughulikiwa.',
        'view_refunds' => 'Tazama Marejesho',
    ],
    'tenant_selection' => 'Uteuzi wa Mpangaji',
    'change' => 'Badilisha',
    'search_placeholder' => 'Tafuta mpangaji kwa jina, simu, au nambari ya kipengele...',
    'no_unit' => 'Hakuna kipengele',
    'payment_selection' => 'Uteuzi wa Malipo',
    'loading_payments' => 'Inapakia malipo...',
    'no_refundable_payments' => 'Hakuna malipo yanayoweza kurejeshwa yaliyopatikana kwa mpangaji huyu',
    'invoice_prefix' => 'Ankara:',
    'of_amount' => 'kati ya {amount}',
    'refund_details' => 'Maelezo ya Marejesho',
    'amount_label' => 'Kiasi *',
    'amount_placeholder' => '0.00',
    'max' => 'Kiwango cha Juu',
    'max_refundable' => 'Kiwango cha juu kinachoweza kurejeshwa: {amount}',
    'refund_method_label' => 'Njia ya Marejesho *',
    'reason_label' => 'Sababu *',
    'select_reason' => 'Chagua sababu...',
    'specify_reason_label' => 'Bainisha Sababu *',
    'custom_reason_placeholder' => 'Weka sababu ya marejesho haya...',
    'notes_label' => 'Madokezo (hiari)',
    'notes_placeholder' => 'Madokezo yoyote ya ziada...',
    'original_payment' => 'Malipo ya Awali',
    'already_refunded' => 'Tayari Yamerejeshwa',
    'this_refund' => 'Marejesho Haya',
    'cancel' => 'Ghairi',
    'processing' => 'Inashughulikiwa...',
    'create_refund' => 'Unda Marejesho',
    'payment_methods' => [
        'cash' => 'Fedha Taslimu',
        'bank_transfer' => 'Uhamisho wa Benki',
        'mobile_money' => 'M-Pesa',
        'paystack' => 'Paystack (Mtandaoni)',
    ],
    'errors' => [
        'select_tenant' => 'Tafadhali chagua mpangaji',
        'select_payment' => 'Tafadhali chagua malipo ya kurejesha',
        'valid_amount' => 'Tafadhali weka kiasi sahihi',
        'amount_exceeds' => 'Kiasi hakiwezi kuzidi {amount}',
        'select_reason' => 'Tafadhali chagua sababu',
        'specify_reason' => 'Tafadhali bainisha sababu',
        'select_method' => 'Tafadhali chagua njia ya marejesho',
    ],
];
