<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Enums\Currency;
use App\Exceptions\CurrencyMismatchException;
use App\Models\Lease;
use App\Models\LeaseWalletBalance;
use App\Models\PaymentConfiguration;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Phase-76 WALLET-DEEP CREDIT-WALLET-1 / MULTI-CCY: the single boundary for
 * tenant wallet credit + apply across currencies.
 *
 * Storage model: the landlord's DEFAULT currency lives in the legacy
 * Lease.wallet_balance scalar (so the ~10 existing readers/writers keep
 * working); every NON-DEFAULT currency lives in a lease_wallet_balances row.
 * The default path delegates to Lease::creditToWallet/deductFromWallet so the
 * transaction + lockForUpdate + afterCommit invariant stays in ONE place; the
 * non-default path locks the balance row itself. Both require an outer
 * transaction.
 */
class WalletService
{
    /**
     * Read-only default-currency lookup — never writes a PaymentConfiguration
     * row (so balance/ledger reads stay side-effect-free).
     */
    public function defaultCurrency(Lease $lease): Currency
    {
        $currency = PaymentConfiguration::where('landlord_id', $lease->landlord_id)->value('default_currency');

        return $currency instanceof Currency ? $currency : Currency::default();
    }

    public function credit(Lease $lease, float $amount, ?string $reason = null, ?int $paymentId = null, ?Currency $currency = null, ?int $creditNoteId = null): void
    {
        $default = $this->defaultCurrency($lease);
        $currency ??= $default;

        if ($currency === $default) {
            $lease->creditToWallet($amount, $reason, $paymentId, $currency, $creditNoteId);

            return;
        }

        $this->mutateNonDefault($lease, $currency, $amount, 'credit', $reason ?? 'Wallet credit', $paymentId, null, $creditNoteId);
    }

    /**
     * Apply wallet credit toward an obligation in $currency. Returns the amount
     * actually drawn (capped at the balance). The currency is the obligation's
     * currency — there is no cross-currency conversion.
     */
    public function apply(Lease $lease, float $amount, ?string $reason = null, ?int $invoiceId = null, ?Currency $currency = null): float
    {
        $default = $this->defaultCurrency($lease);
        $currency ??= $default;

        if ($currency === $default) {
            return $lease->deductFromWallet($amount, $reason, $invoiceId, $currency);
        }

        return $this->mutateNonDefault($lease, $currency, $amount, 'debit', $reason ?? 'Applied to invoice', null, $invoiceId, null) ?? 0.0;
    }

    /**
     * Guard a cross-currency application: a wallet balance in $walletCurrency may
     * only settle an obligation in the same currency.
     */
    public function assertSameCurrency(Currency $walletCurrency, Currency $targetCurrency): void
    {
        if ($walletCurrency !== $targetCurrency) {
            throw new CurrencyMismatchException($walletCurrency->value, $targetCurrency->value);
        }
    }

    public function balanceFor(Lease $lease, ?Currency $currency = null): float
    {
        $currency ??= $this->defaultCurrency($lease);

        if ($currency === $this->defaultCurrency($lease)) {
            return (float) $lease->wallet_balance;
        }

        $row = LeaseWalletBalance::where('lease_id', $lease->id)
            ->where('currency', $currency->value)
            ->first();

        return (float) ($row->balance ?? 0);
    }

    /**
     * Non-zero balances keyed by currency code.
     *
     * @return array<string, float>
     */
    public function balancesFor(Lease $lease): array
    {
        $default = $this->defaultCurrency($lease);
        $balances = [];

        $defaultBalance = (float) $lease->wallet_balance;
        if (abs($defaultBalance) > 0.001) {
            $balances[$default->value] = $defaultBalance;
        }

        LeaseWalletBalance::where('lease_id', $lease->id)
            ->where('currency', '!=', $default->value)
            ->get()
            ->each(function (LeaseWalletBalance $row) use (&$balances) {
                $balance = (float) $row->balance;
                if (abs($balance) > 0.001) {
                    $balances[$row->currency->value] = $balance;
                }
            });

        return $balances;
    }

    /**
     * @return Builder<WalletTransaction>
     */
    public function ledger(Lease $lease, ?Currency $currency = null): Builder
    {
        return WalletTransaction::query()
            ->where('lease_id', $lease->id)
            ->when($currency, fn (Builder $q) => $q->where('currency', $currency->value))
            ->orderByDesc('id');
    }

    private function mutateNonDefault(Lease $lease, Currency $currency, float $amount, string $type, string $reason, ?int $paymentId, ?int $invoiceId, ?int $creditNoteId): ?float
    {
        throw_unless(DB::transactionLevel() > 0, \LogicException::class, 'WalletService non-default mutation must be called within a transaction');

        // Create-then-lock: firstOrCreate's race-recovery re-read is NOT locked,
        // so re-fetch FOR UPDATE before the arithmetic to close the lost-update
        // window on the first concurrent creation of a (lease, currency) row.
        LeaseWalletBalance::firstOrCreate(
            ['lease_id' => $lease->id, 'currency' => $currency->value],
            ['landlord_id' => $lease->landlord_id, 'balance' => 0],
        );

        $row = LeaseWalletBalance::where('lease_id', $lease->id)
            ->where('currency', $currency->value)
            ->lockForUpdate()
            ->firstOrFail();

        if ($type === 'debit') {
            $deducted = min($amount, (float) $row->balance);
            if ($deducted <= 0) {
                return 0.0;
            }
            $newBalance = (float) $row->balance - $deducted;
            $applied = $deducted;
        } else {
            $newBalance = (float) $row->balance + $amount;
            $applied = $amount;
        }

        $row->balance = $newBalance;
        $row->save();

        WalletTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'type' => $type,
            'amount' => $applied,
            'reason' => $reason,
            'balance_after' => $newBalance,
            'invoice_id' => $invoiceId,
            'payment_id' => $paymentId,
            'credit_note_id' => $creditNoteId,
            'currency' => $currency->value,
        ]);

        return $applied;
    }
}
