<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\Currency;
use App\Models\PaymentConfiguration;
use App\ValueObjects\Money;

/**
 * Phase-42 TAX: KRA VAT + Stripe Tax helpers. Phase 42 ships VAT
 * TRACKING — the actual filing/submission against the KRA iTax
 * portal is deferred to Phase 43 (multi-week regulatory work).
 *
 * Two modes:
 *   - Kenyan VAT (currency=KES): we compute 16% locally via bcmath
 *     and stamp tax_amount_cents on the invoice item. KRA-registered
 *     landlords (kra_pin set) get a PIN-stamped invoice.
 *   - Non-KES via Stripe Tax: we defer to Stripe's automatic_tax
 *     parameter on the PaymentIntent. Landlord opts in per-config
 *     via payment_configurations.stripe_tax_enabled.
 */
final class StripeTaxService
{
    public const KENYA_VAT_RATE_BPS = 1600;

    public function vatRateBpsFor(PaymentConfiguration $config): int
    {
        return $config->vat_rate_bps_override ?? self::KENYA_VAT_RATE_BPS;
    }

    public function computeKenyanVat(Money $subtotal, ?PaymentConfiguration $config = null): Money
    {
        $bps = $config !== null ? $this->vatRateBpsFor($config) : self::KENYA_VAT_RATE_BPS;
        $factor = bcdiv((string) $bps, '10000', 6);

        return $subtotal->multiply($factor);
    }

    /**
     * Build the canonical tax line-item shape used by both invoice
     * persistence and the Stripe PaymentIntent metadata. For KES we
     * compute locally; everything else returns null and the caller
     * is expected to pass automatic_tax=enabled to Stripe instead.
     *
     * @return array{description: string, amount_cents: int, rate_bps: int}|null
     */
    public function vatLineItem(Money $subtotal, Currency $currency, ?PaymentConfiguration $config = null): ?array
    {
        if ($currency !== Currency::KES) {
            return null;
        }

        $vat = $this->computeKenyanVat($subtotal, $config);
        $bps = $config !== null ? $this->vatRateBpsFor($config) : self::KENYA_VAT_RATE_BPS;

        return [
            'description' => __('payments.tax.vat_label'),
            'amount_cents' => $vat->toMinorUnits(),
            'rate_bps' => $bps,
        ];
    }

    /**
     * Stripe Tax automatic_tax opt-in resolves to true only when the
     * landlord has explicitly enabled it. Kenya VAT is computed locally
     * regardless — Stripe Tax doesn't yet support KE jurisdictions.
     */
    public function stripeAutomaticTaxFor(PaymentConfiguration $config, Currency $currency): bool
    {
        if ($currency === Currency::KES) {
            return false;
        }

        return (bool) ($config->stripe_tax_enabled ?? false);
    }
}
