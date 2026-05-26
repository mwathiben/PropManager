<?php

declare(strict_types=1);

/**
 * Phase-105+ i18n migration: tenants page payment-verifications tab. Mirror en/sw/ar.
 */
return [
    'filters' => [
        'search_placeholder' => 'Tafuta uthibitisho wa malipo...',
        'all_status' => 'Hali Zote',
        'clear' => 'Futa vichujio',
    ],
    'status' => [
        'pending' => 'Inasubiri',
        'approved' => 'Imeidhinishwa',
        'rejected' => 'Imekataliwa',
    ],
    'table' => [
        'tenant' => 'Mpangaji',
        'unit' => 'Chumba',
        'amount' => 'Kiasi',
        'status' => 'Hali',
        'actions' => 'Vitendo',
    ],
    'actions' => [
        'view' => 'Tazama',
    ],
    'empty' => [
        'title' => 'Hakuna uthibitisho wa malipo',
        'description_filtered' => 'Jaribu kurekebisha vichujio vyako.',
        'description_default' => 'Uthibitisho wa malipo utaonekana hapa.',
    ],
    'pagination' => [
        'showing' => 'Inaonyesha {from} hadi {to} kati ya {total} matokeo',
    ],
    'unknown' => 'Haijulikani',
    'unit_prefix' => 'Chumba {number}',
];
