<?php

declare(strict_types=1);

/**
 * Phase-81 FINANCE-DEPTH lang namespace. Parity: mirror en / sw / ar exactly.
 */

return [
    'deposit_settlement' => [
        'arrears_offset' => 'Malipo ya madeni',
        'refund_reason' => 'Marejesho ya amana (utatuzi wa kuondoka)',
        'move_out_reason' => 'Utatuzi wa kuondoka #:id',
        'received_reason' => 'Amana imepokelewa',
        'title' => 'Utatuzi wa amana',
        'held' => 'Amana iliyoshikwa',
        'deductions' => 'Makato',
        'arrears' => 'Madeni',
        'refund' => 'Marejesho',
    ],
    'period_close' => [
        'blocked' => 'Kipindi hiki hakiwezi kufungwa bado.',
        'draft_invoices' => 'Ankara za rasimu katika kipindi hiki',
        'pending_reconciliation' => 'Vipengele vya upatanishi wa benki vinavyosubiri',
        'ready' => 'Tayari kufungwa',
        'force' => 'Funga hata hivyo',
    ],
    'arrears' => [
        'aging' => [
            'title' => 'Uchanganuzi wa madeni kwa mpangaji',
            'bucket_0_30' => 'Siku 0–30',
            'bucket_31_60' => 'Siku 31–60',
            'bucket_61_90' => 'Siku 61–90',
            'bucket_90_plus' => 'Siku 90+',
            'days_overdue' => 'Siku zilizochelewa',
        ],
    ],
    'bank_recon' => [
        'imported' => 'Miamala :count imeingizwa, :skipped imerukwa.',
        'processed' => ':matched zimelinganishwa, :unmatched hazijalinganishwa.',
        'matched' => 'Malipo yamelinganishwa.',
    ],
    'late_fee' => [
        'applied' => 'Ada :count za kuchelewa zimewekwa.',
        'projected' => 'Ada ya kuchelewa inayotarajiwa ikiwa haijalipwa kufikia :date',
        'apply_now' => 'Weka ada za kuchelewa sasa',
    ],
];
