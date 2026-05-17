<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Models\PaymentConfiguration;
use App\Traits\LogsExternalRequests;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use Carbon\CarbonImmutable;
use Stripe\Webhook;

/**
 * Phase-40 GATEWAY-STRIPE-1: per-landlord Stripe SDK client wrapper.
 * Credentials load from PaymentConfiguration (NOT from .env — follows
 * the Paystack pattern documented in docs/runbooks/payments.md).
 *
 * The system-wide SaaS billing path lives in StripeSubscriptionService
 * (loads from Setting::getSystem) — the two services MUST stay
 * separate to preserve credential isolation between landlord-facing
 * rent collection and PropManager's own subscription revenue.
 */
class StripeService
{
    use LogsExternalRequests;

    protected string $secretKey = '';

    protected string $publicKey = '';

    protected string $webhookSecret = '';

    protected ?StripeClient $client = null;

    protected ?PaymentConfiguration $config = null;

    public function __construct(?PaymentConfiguration $config = null)
    {
        if ($config !== null && $config->hasStripeConfig()) {
            $this->loadCredentials($config);
        }
    }

    public function withConfig(PaymentConfiguration $config): self
    {
        if (! $config->hasStripeConfig()) {
            throw new \InvalidArgumentException(
                'StripeService requires a PaymentConfiguration with Stripe credentials. '
                .'Configure in Settings > Payment Methods.'
            );
        }

        $this->loadCredentials($config);

        return $this;
    }

    private function loadCredentials(PaymentConfiguration $config): void
    {
        $this->secretKey = (string) $config->stripe_secret_key;
        $this->publicKey = (string) $config->stripe_public_key;
        $this->webhookSecret = (string) ($config->stripe_webhook_secret ?? '');
        $this->config = $config;
        $this->client = null;
    }

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \InvalidArgumentException(
                'StripeService requires Stripe credentials. Call withConfig() first or construct with PaymentConfiguration.'
            );
        }
    }

    protected function client(): StripeClient
    {
        $this->ensureConfigured();
        if ($this->client === null) {
            $this->client = new StripeClient($this->secretKey);
        }

        return $this->client;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publicKey !== '';
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey === '' ? null : $this->publicKey;
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret === '' ? null : $this->webhookSecret;
    }

    /**
     * Create a PaymentIntent for the given amount.
     *
     * @param  array{amount: int, currency: string, reference: string, metadata?: array, receipt_email?: string}  $data
     * @return array|null
     */
    public function createPaymentIntent(array $data): ?array
    {
        $this->ensureConfigured();

        $payload = [
            'amount' => (int) $data['amount'],
            'currency' => strtolower($data['currency']),
            'metadata' => array_merge($data['metadata'] ?? [], ['reference' => $data['reference']]),
            'receipt_email' => $data['receipt_email'] ?? null,
            'automatic_payment_methods' => ['enabled' => true],
        ];

        // Phase-42 TAX-3: opt-in Stripe Tax for non-KES landlords who
        // have stripe_tax_enabled set on their PaymentConfiguration.
        // Stripe Tax doesn't yet cover Kenya — KES uses Phase-42
        // local VAT computation via StripeTaxService instead.
        if ($this->config !== null
            && $this->config->hasStripeTaxEnabled()
            && strtolower($data['currency']) !== 'kes'
        ) {
            $payload['automatic_tax'] = ['enabled' => true];
        }

        try {
            $intent = $this->timedHttpRequest('stripe', '/v1/payment_intents', fn () => $this->client()->paymentIntents->create($payload));

            return [
                'status' => true,
                'data' => [
                    'reference' => $intent->id,
                    'client_secret' => $intent->client_secret,
                    'amount' => $intent->amount,
                    'currency' => strtoupper($intent->currency),
                    'status' => $intent->status,
                ],
            ];
        } catch (ApiErrorException $e) {
            Log::warning('stripe createPaymentIntent failed', [
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            throw new PaymentGatewayUnreachableException('stripe', $e->getMessage(), 'unreachable');
        }
    }

    public function retrievePaymentIntent(string $paymentIntentId): ?array
    {
        $this->ensureConfigured();

        try {
            $intent = $this->client()->paymentIntents->retrieve($paymentIntentId);

            return [
                'status' => true,
                'data' => [
                    'reference' => $intent->id,
                    'status' => $intent->status,
                    'amount' => $intent->amount,
                    'currency' => strtoupper($intent->currency),
                ],
            ];
        } catch (ApiErrorException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Phase-41 GATEWAY-RECONCILE-DEEP-1: paginated remote charge fetch
     * for reconciliation. Returns array keyed by charge.id with the
     * raw Stripe Charge objects so PaymentReconciliationService can
     * normalise via TransactionAdapter::fromStripe.
     */
    public function listCharges(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->ensureConfigured();

        $result = [];
        $startingAfter = null;
        do {
            $params = [
                'created' => ['gte' => $from->getTimestamp(), 'lte' => $to->getTimestamp()],
                'limit' => 100,
            ];
            if ($startingAfter !== null) {
                $params['starting_after'] = $startingAfter;
            }

            try {
                $page = $this->client()->charges->all($params);
            } catch (ApiErrorException $e) {
                Log::warning('stripe listCharges failed', ['error' => $e->getMessage()]);
                break;
            }

            foreach ($page->data as $charge) {
                $result[$charge->id] = $charge;
            }

            $hasMore = (bool) ($page->has_more ?? false);
            $startingAfter = $hasMore && ! empty($page->data)
                ? end($page->data)->id
                : null;
        } while ($startingAfter !== null);

        return $result;
    }

    public function refund(string $paymentIntentId, ?int $amountMinorUnits = null): ?array
    {
        $this->ensureConfigured();

        try {
            $payload = ['payment_intent' => $paymentIntentId];
            if ($amountMinorUnits !== null) {
                $payload['amount'] = $amountMinorUnits;
            }
            $refund = $this->client()->refunds->create($payload);

            return [
                'status' => true,
                'amount' => $refund->amount,
                'currency' => strtoupper($refund->currency),
                'reference' => $refund->id,
                'payment_intent' => $refund->payment_intent,
            ];
        } catch (ApiErrorException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify Stripe webhook signature using the configured webhook secret.
     */
    public function verifyWebhookSignature(string $rawPayload, string $sigHeader): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }
        try {
            Webhook::constructEvent($rawPayload, $sigHeader, $this->webhookSecret);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function generateReference(string $prefix = 'PAY'): string
    {
        return strtoupper($prefix.'_'.bin2hex(random_bytes(8)));
    }
}
