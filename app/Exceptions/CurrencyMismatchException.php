<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Phase-76 WALLET-DEEP MULTI-CCY-2: thrown when a caller tries to apply wallet
 * credit held in one currency to an obligation denominated in another. There is
 * no FX engine — applying USD credit to a KES invoice 1:1 would silently destroy
 * money, so we refuse rather than guess a rate. Rendered as 422.
 */
class CurrencyMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $walletCurrency,
        public readonly string $targetCurrency,
    ) {
        parent::__construct(
            "Cannot apply {$walletCurrency} wallet credit to a {$targetCurrency} obligation — currencies must match.",
        );
    }

    public function render(): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $key = 'wallet.errors.currency_mismatch';
        $message = __($key, [
            'wallet' => $this->walletCurrency,
            'target' => $this->targetCurrency,
        ]);

        if ($message === $key) {
            $message = $this->getMessage();
        }

        if (request()->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['wallet' => $message]);
    }
}
