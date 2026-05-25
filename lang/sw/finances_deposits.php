<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub deposits tab. Mirror en/sw/ar.
 */
return [
    'metric' => [
        'total' => 'Jumla ya Amana',
        'held' => 'Zinazoshikiliwa Sasa',
        'refunded' => 'Zilizorejeshwa',
        'forfeited' => 'Zilizotaifishwa',
    ],
    'search_placeholder' => 'Tafuta amana...',
    'empty' => [
        'title' => 'Hakuna amana zilizopatikana',
        'description' => 'Amana za usalama zitaonekana hapa',
    ],
    'column' => [
        'tenant' => 'Mpangaji',
        'unit' => 'Nyumba',
        'amount' => 'Kiasi',
        'status' => 'Hali',
        'collected' => 'Zilizokusanywa',
    ],
    'status' => [
        'held' => 'Zinashikiliwa',
        'refunded' => 'Zilizorejeshwa',
        'forfeited' => 'Zilizotaifishwa',
        'partial_refund' => 'Marejesho ya Sehemu',
    ],
    'status_label' => [
        'held' => 'Zinashikiliwa',
        'refunded' => 'Zilizorejeshwa',
        'forfeited' => 'Zilizotaifishwa',
        'partial' => 'Sehemu',
    ],
    'refunded_amount' => 'Zilizorejeshwa: {amount}',
    'processed' => 'Zilishughulikiwa: {date}',
    'action' => [
        'refund' => 'Rejesha Amana',
        'forfeit' => 'Taifisha Amana',
    ],
    'transaction_history' => 'Historia ya Miamala',
    'transaction_history_for' => 'Historia ya Miamala - {tenant} ({unit})',
    'by' => 'na {name}',
];
