<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionItem;
use App\Services\PaymentGatewayManager;
use App\Services\StripeService;
use App\ValueObjects\Payment\Money as PaymentMoney;
use App\ValueObjects\Payment\PaymentRequest;
use Illuminate\Support\Facades\Log;

/**
 * Phase-42 CART-2: groups CheckoutSession items by currency and
 * creates one Stripe PaymentIntent per currency group. Returns
 * an array keyed by currency carrying {client_secret, amount,
 * payment_intent_id} so the tenant frontend can render one
 * Stripe Elements form per group.
 *
 * Phase 42 explicitly does NOT ship FX conversion — a single
 * checkout that carries KES rent + USD add-on remains two
 * separate Stripe charges. Cross-currency netting is a future
 * cycle (Phase 44+).
 */
final class CartCheckoutService
{
    public function __construct(private readonly PaymentGatewayManager $gatewayManager) {}

    /**
     * @return array<string, array{client_secret: ?string, amount_cents: int, payment_intent_id: ?string, line_count: int}>
     */
    public function initialize(CheckoutSession $session): array
    {
        $session->loadMissing('items', 'landlord');

        if ($session->items->isEmpty()) {
            return [];
        }

        $groups = $session->items->groupBy(fn (CheckoutSessionItem $i) => strtoupper($i->currency));
        $result = [];

        foreach ($groups as $currency => $items) {
            $amount = (int) $items->sum('amount_cents');
            if ($amount <= 0) {
                continue;
            }

            $reference = StripeService::generateReference('CART-'.$currency);
            $intent = $this->createIntentForGroup($session, (string) $currency, $amount, $reference, $items);

            $result[(string) $currency] = [
                'client_secret' => $intent['client_secret'] ?? null,
                'amount_cents' => $amount,
                'payment_intent_id' => $intent['payment_intent_id'] ?? null,
                'line_count' => $items->count(),
            ];
        }

        $session->update([
            'status' => CheckoutSession::STATUS_SUBMITTED,
            'currency_breakdown' => $this->breakdownSummary($result),
            'total_amount_cents' => array_sum(array_column($result, 'amount_cents')),
        ]);

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CheckoutSessionItem>  $items
     * @return array{client_secret: ?string, payment_intent_id: ?string}
     */
    private function createIntentForGroup(CheckoutSession $session, string $currency, int $amountCents, string $reference, $items): array
    {
        $landlord = $session->landlord;
        if ($landlord === null) {
            return ['client_secret' => null, 'payment_intent_id' => null];
        }

        try {
            $gateway = $this->gatewayManager->routeForUser($landlord, $currency);
        } catch (\Throwable $e) {
            Log::warning('CartCheckoutService routeForUser failed', [
                'session_id' => $session->id,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            return ['client_secret' => null, 'payment_intent_id' => null];
        }

        if (! ($gateway instanceof \App\Contracts\PaymentGatewayInterface)) {
            return ['client_secret' => null, 'payment_intent_id' => null];
        }

        $request = new PaymentRequest(
            amount: PaymentMoney::fromSmallestUnit($amountCents, $currency),
            reference: $reference,
            email: $session->tenant?->email,
            description: __('payments.cart.line_description', ['session' => $session->id, 'currency' => $currency]),
            metadata: [
                'checkout_session_id' => $session->id,
                'currency_group' => $currency,
            ],
        );

        try {
            $result = $gateway->initializePayment($request);
        } catch (\Throwable $e) {
            Log::warning('CartCheckoutService gateway initialize failed', [
                'session_id' => $session->id,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            return ['client_secret' => null, 'payment_intent_id' => null];
        }

        if (! $result->isSuccessful()) {
            return ['client_secret' => null, 'payment_intent_id' => null];
        }

        // Stripe stashes client_secret in accessCode, payment intent id
        // in reference. Paystack returns authorizationUrl instead.
        $paymentIntentId = $result->reference;
        $clientSecret = $result->accessCode ?? $result->authorizationUrl;

        // Stamp the payment intent id back onto every line in the group
        // so reconciliation can fan out a successful intent to its lines.
        if ($paymentIntentId !== null) {
            CheckoutSessionItem::query()
                ->where('checkout_session_id', $session->id)
                ->where('currency', $currency)
                ->update(['stripe_payment_intent_id' => $paymentIntentId]);
        }

        return [
            'client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
        ];
    }

    /**
     * @param  array<string, array{amount_cents: int, line_count: int}>  $result
     */
    private function breakdownSummary(array $result): array
    {
        $summary = [];
        foreach ($result as $currency => $row) {
            $summary[$currency] = [
                'amount_cents' => $row['amount_cents'],
                'line_count' => $row['line_count'],
            ];
        }

        return $summary;
    }
}
