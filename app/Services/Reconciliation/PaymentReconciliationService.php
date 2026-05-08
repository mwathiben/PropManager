<?php

declare(strict_types=1);

namespace App\Services\Reconciliation;

use App\Enums\Currency;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\PaystackService;
use App\ValueObjects\ReconciliationDiscrepancy;
use App\ValueObjects\ReconciliationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        protected PaystackService $paystackService,
    ) {}

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
        $payments = Payment::withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
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
