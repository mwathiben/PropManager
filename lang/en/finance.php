<?php

declare(strict_types=1);

/**
 * Phase-81 FINANCE-DEPTH lang namespace. Parity: mirror en / sw / ar exactly.
 */

return [
    'deposit_settlement' => [
        'arrears_offset' => 'Arrears offset',
        'refund_reason' => 'Deposit refund (move-out settlement)',
        'move_out_reason' => 'Move-out #:id settlement',
        'received_reason' => 'Deposit received',
        'title' => 'Deposit settlement',
        'held' => 'Deposit held',
        'deductions' => 'Deductions',
        'arrears' => 'Arrears',
        'refund' => 'Refund',
    ],
    'period_close' => [
        'blocked' => 'This period cannot be closed yet.',
        'draft_invoices' => 'Draft invoices in this period',
        'pending_reconciliation' => 'Pending bank reconciliation items',
        'ready' => 'Ready to close',
        'force' => 'Close anyway',
    ],
    'arrears' => [
        'aging' => [
            'title' => 'Arrears aging by tenant',
            'bucket_0_30' => '0–30 days',
            'bucket_31_60' => '31–60 days',
            'bucket_61_90' => '61–90 days',
            'bucket_90_plus' => '90+ days',
            'days_overdue' => 'Days overdue',
        ],
    ],
    'bank_recon' => [
        'imported' => ':count transaction(s) imported, :skipped skipped.',
        'processed' => ':matched matched, :unmatched unmatched.',
        'matched' => 'Payment matched.',
    ],
    'late_fee' => [
        'applied' => ':count late fee(s) applied.',
        'projected' => 'Projected late fee if unpaid by :date',
        'apply_now' => 'Apply late fees now',
    ],
];
