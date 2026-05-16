<?php

declare(strict_types=1);

namespace App\Services\Reconciliation;

use Stripe\Charge;

/**
 * Phase-41 GATEWAY-RECONCILE-DEEP-3: normalises per-gateway txn
 * shapes (Paystack array vs Stripe Charge object) into a canonical
 * {reference, amount_minor, currency, status} dict so
 * PaymentReconciliationService::compareLedgers can be gateway-agnostic.
 */
class TransactionAdapter
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{reference: string, amount_minor: int, currency: string, status: string}
     */
    public static function fromPaystack(array $row): array
    {
        return [
            'reference' => (string) ($row['reference'] ?? ''),
            'amount_minor' => (int) ($row['amount'] ?? 0),
            'currency' => strtoupper((string) ($row['currency'] ?? 'KES')),
            'status' => (string) ($row['status'] ?? 'unknown'),
        ];
    }

    /**
     * @return array{reference: string, amount_minor: int, currency: string, status: string}
     */
    public static function fromStripe(Charge $charge): array
    {
        return [
            'reference' => (string) $charge->id,
            'amount_minor' => (int) $charge->amount,
            'currency' => strtoupper((string) $charge->currency),
            'status' => (string) $charge->status,
        ];
    }
}
