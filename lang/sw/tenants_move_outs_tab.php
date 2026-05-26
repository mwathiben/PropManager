<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page move-outs tab. Mirror en/sw/ar.
 */
return [
    'stats' => [
        'active' => 'Hai',
        'inspection_pending' => 'Ukaguzi unasubiri',
        'settlement_pending' => 'Malipo yanasubiri',
        'completed_this_month' => 'Imekamilika (mwezi)',
    ],
    'filter' => [
        'active' => 'Hai',
        'completed' => 'Imekamilika',
    ],
    'table' => [
        'tenant' => 'Mpangaji',
        'unit' => 'Chumba',
        'initiated' => 'Imeanzishwa',
        'status' => 'Hali',
        'actions' => 'Vitendo',
    ],
    'status' => [
        'notice_given' => 'taarifa imetolewa',
        'inspection_pending' => 'ukaguzi unasubiri',
        'inspection_complete' => 'ukaguzi umekamilika',
        'settlement_pending' => 'malipo yanasubiri',
        'completed' => 'imekamilika',
        'settled' => 'imelipwa',
        'cancelled' => 'imeghairiwa',
    ],
    'actions' => [
        'view' => 'Tazama',
    ],
    'empty' => [
        'title' => 'Hakuna kuhama',
        'description' => 'Kesi za kuhama zitaonekana hapa.',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya {total} matokeo',
    ],
    'unknown' => 'Haijulikani',
    'unit_prefix' => 'Chumba {number}',
];
