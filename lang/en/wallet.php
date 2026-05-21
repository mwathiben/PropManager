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
];
