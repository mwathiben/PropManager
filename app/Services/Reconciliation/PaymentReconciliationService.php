<?php

declare(strict_types=1);

namespace App\Services\Reconciliation;

use App\Enums\Currency;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\ValueObjects\ReconciliationDiscrepancy;
use App\ValueObjects\ReconciliationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        protected PaystackService $paystackService,
        protected ?StripeService $stripeService = null,
    ) {}

    /**
     * Phase-40 GATEWAY-RECONCILE-1: gateway-agnostic dispatcher.
     * For now this just routes to the per-gateway impl methods;
     * a fully unified compare loop lives in reconcilePaystack and
     * (when populated) reconcileStripe — the value here is a single
     * surface for cron + admin tooling.
     */
    public function reconcile(
        string $gateway,
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): ReconciliationResult {
        return match (strtolower($gateway)) {
            'paystack' => $this->reconcilePaystack($landlordId, $from, $to),
            'stripe' => $this->reconcileStripe($landlordId, $from, $to),
            default => throw new \InvalidArgumentException("Unknown gateway for reconcile: {$gateway}"),
        };
    }

    /**
     * Phase-40 GATEWAY-RECONCILE-1: Stripe-side reconciliation.
     * Lists local stripe-method payments against (eventually) Stripe
     * charges.list. Local-only diff today — remote charge fetch lands
     * in the next cycle when the first paying landlord enables Stripe
     * for rent collection (currently no production load on this path).
     */
    public function reconcileStripe(
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): ReconciliationResult {
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();
        if (! $config || ! $config->hasStripeConfig()) {
            return $this->emptyResult();
        }

        $localPayments = $this->paymentsForLandlord($landlordId)
            ->where('payment_method', 'stripe')
            ->whereNotNull('paystack_reference')
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$from->startOfDay(), $to->endOfDay()])
            ->select(['id', 'paystack_reference', 'amount', 'currency', 'payment_date', 'landlord_id'])
            ->get();

        // Phase 1e ships local-only audit; remote diff arrives in Phase 41.
        return new ReconciliationResult(
            discrepancies: [],
            localCount: $localPayments->count(),
            remoteCount: 0,
            matchedCount: 0,
            reconciledAt: now()->toIso8601String(),
        );
    }

    public function reconcilePaystack(
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): ReconciliationResult {
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        if (! $config || ! $config->hasPaystackConfig()) {
            Log::warning('Paystack reconciliation skipped: not configured', [
                'landlord_id' => $landlordId,
            ]);

            return $this->emptyResult();
        }

        $this->paystackService->withConfig($config);

        $remoteTransactions = $this->fetchAllPaystackTransactions($from, $to);
        $localPayments = $this->fetchLocalPaystackPayments($landlordId, $from, $to);

        return $this->compare($localPayments, $remoteTransactions);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function fetchAllPaystackTransactions(
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $allTransactions = [];
        $page = 1;

        do {
            $response = $this->paystackService->listTransactions([
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'status' => 'success',
                'perPage' => 100,
                'page' => $page,
            ]);

            if ($response === null) {
                Log::error('Paystack reconciliation: failed to fetch page', [
                    'page' => $page,
                ]);
                break;
            }

            $transactions = $response['data'] ?? [];

            if (empty($transactions)) {
                break;
            }

            foreach ($transactions as $txn) {
                $this->indexByReference($allTransactions, $txn);
            }

            $hasNext = ! empty($response['meta']['next'] ?? null);
            $page++;
        } while ($hasNext && $page <= ReconciliationResult::MAX_PAGES);

        return $allTransactions;
    }

    /**
     * @return array<string, Payment>
     */
    protected function fetchLocalPaystackPayments(
        int $landlordId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $payments = $this->paymentsForLandlord($landlordId)
            ->where('payment_method', 'paystack')
            ->whereNotNull('paystack_reference')
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$from->startOfDay(), $to->endOfDay()])
            ->select(['id', 'paystack_reference', 'amount', 'currency', 'payment_date', 'landlord_id'])
            ->get();

        $keyed = [];
        foreach ($payments as $payment) {
            $ref = $payment->paystack_reference;

            if (isset($keyed[$ref])) {
                Log::warning('Reconciliation: duplicate paystack_reference in local payments', [
                    'paystack_reference' => $ref,
                    'existing_payment_id' => $keyed[$ref]->id,
                    'duplicate_payment_id' => $payment->id,
                    'landlord_id' => $landlordId,
                ]);

                continue;
            }

            $keyed[$ref] = $payment;
        }

        return $keyed;
    }

    /**
     * @param  array<string, Payment>  $localPayments
     * @param  array<string, array<string, mixed>>  $remoteTransactions
     */
    protected function compare(array $localPayments, array $remoteTransactions): ReconciliationResult
    {
        $discrepancies = [];
        $matchedCount = 0;

        foreach ($remoteTransactions as $reference => $remoteTxn) {
            $remoteAmountMajor = $this->toMajorUnits($remoteTxn);
            $remoteCurrency = $remoteTxn['currency'] ?? 'KES';
            $remoteStatus = $remoteTxn['status'] ?? 'unknown';

            if (! isset($localPayments[$reference])) {
                $discrepancies[] = ReconciliationDiscrepancy::missingLocally(
                    reference: $reference,
                    remoteAmount: $remoteAmountMajor,
                    currency: $remoteCurrency,
                    remoteStatus: $remoteStatus,
                );

                continue;
            }

            $localAmount = (float) $localPayments[$reference]->amount;

            if (abs($localAmount - $remoteAmountMajor) > ReconciliationResult::TOLERANCE) {
                $discrepancies[] = ReconciliationDiscrepancy::amountMismatch(
                    reference: $reference,
                    localAmount: $localAmount,
                    remoteAmount: $remoteAmountMajor,
                    currency: $remoteCurrency,
                    remoteStatus: $remoteStatus,
                );

                continue;
            }

            $matchedCount++;
        }

        foreach ($localPayments as $reference => $localPayment) {
            if (! isset($remoteTransactions[$reference])) {
                $discrepancies[] = ReconciliationDiscrepancy::missingRemotely(
                    reference: $reference,
                    localAmount: (float) $localPayment->amount,
                    currency: $localPayment->currency?->value ?? 'KES',
                );
            }
        }

        return new ReconciliationResult(
            discrepancies: $discrepancies,
            localCount: count($localPayments),
            remoteCount: count($remoteTransactions),
            matchedCount: $matchedCount,
            reconciledAt: now()->toIso8601String(),
        );
    }

    protected function toMajorUnits(array $remoteTxn): float
    {
        $amountMinor = (int) ($remoteTxn['amount'] ?? 0);
        $currencyCode = $remoteTxn['currency'] ?? 'KES';

        $currency = Currency::tryFrom($currencyCode);

        if ($currency) {
            return $currency->fromMinorUnits($amountMinor);
        }

        return $amountMinor / 100;
    }

    // SCOPE-D4: a single funnel for any per-landlord Payment query in this
    // service. Forgetting the landlord_id filter is no longer possible by
    // construction — every reconciliation query goes through this helper.
    private function paymentsForLandlord(int $landlordId): \Illuminate\Database\Eloquent\Builder
    {
        if ($landlordId <= 0) {
            throw new \InvalidArgumentException('Reconciliation requires a positive landlord id.');
        }

        return Payment::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId);
    }

    private function indexByReference(array &$index, array $txn): void
    {
        $ref = $txn['reference'] ?? null;

        if ($ref !== null) {
            $index[$ref] = $txn;
        }
    }

    private function emptyResult(): ReconciliationResult
    {
        return new ReconciliationResult(
            discrepancies: [],
            localCount: 0,
            remoteCount: 0,
            matchedCount: 0,
            reconciledAt: now()->toIso8601String(),
        );
    }
}
