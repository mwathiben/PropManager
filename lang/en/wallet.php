<?php

declare(strict_types=1);

/**
 * Phase-76 WALLET-DEEP wallet lang namespace (landlord settings + errors).
 * Tenant-facing wallet strings live under tenant.wallet.*.
 *
 * Parity contract: keys MUST mirror across en / sw / ar exactly in order +
 * nesting (Phase 24 CI watchdog does identity comparison on array_keys).
 */

return [
    'errors' => [
        'currency_mismatch' => 'Cannot apply :wallet wallet credit to a :target obligation — currencies must match.',
    ],
    'settings' => [
        'title' => 'Wallet auto-apply',
        'subtitle' => 'Control how standing tenant wallet credit is applied to invoices',
        'mode_label' => 'Auto-apply mode',
        'mode_off' => 'Off — never auto-apply',
        'mode_on_invoice_create' => 'On invoice creation',
        'mode_oldest_first_sweep' => 'Daily sweep (oldest unpaid first)',
        'save' => 'Save settings',
        'saved' => 'Wallet settings saved.',
    ],
];
