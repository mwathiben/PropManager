<?php

declare(strict_types=1);

return [
    /*
     | Phase-76 WALLET-DEEP AUTO-APPLY: how a landlord's standing tenant wallet
     | credit is applied to invoices when no per-landlord override exists.
     |   off                -> never auto-apply (manual / tenant self-apply only)
     |   on_invoice_create  -> deduct wallet credit when an invoice is generated
     |   oldest_first_sweep -> leave invoices at create; the wallet:auto-apply
     |                         cron applies standing credit oldest-unpaid-first
     */
    'default_auto_apply_mode' => env('WALLET_DEFAULT_AUTO_APPLY_MODE', 'on_invoice_create'),

    'auto_apply_modes' => ['off', 'on_invoice_create', 'oldest_first_sweep'],
];
