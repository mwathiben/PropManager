<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: finances hub refunds tab. Mirror en/sw/ar.
 */
return [
    'search_placeholder' => 'Tafuta marejesho...',
    'actions' => [
        'process_refund' => 'Chakata Marejesho',
        'view' => 'Tazama',
    ],
    'columns' => [
        'payment_ref' => 'Rejea ya Malipo',
        'tenant' => 'Mpangaji',
        'amount' => 'Kiasi',
        'reason' => 'Sababu',
        'status' => 'Hali',
        'requested' => 'Iliombwa',
    ],
    'fallbacks' => [
        'unknown_tenant' => 'Haijulikani',
        'no_unit' => 'Haipatikani',
    ],
    'status' => [
        'pending' => 'Inasubiri',
        'approved' => 'Imeidhinishwa',
        'processing' => 'Inachakatwa',
        'completed' => 'Imekamilika',
        'failed' => 'Imeshindwa',
        'cancelled' => 'Imeghairiwa',
    ],
    'empty' => [
        'title' => 'Hakuna marejesho yaliyopatikana',
        'description' => 'Maombi ya marejesho yataonekana hapa',
    ],
];
