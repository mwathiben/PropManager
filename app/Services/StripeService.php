<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Exceptions\Integration\PaymentGatewayUnreachableException;
use App\Models\PaymentConfiguration;
use App\Traits\LogsExternalRequests;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
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
     */
    public function createPaymentIntent(array $data): ?array
    {
        $this->ensureConfigured();

        $payload = $this->buildPaymentIntentPayload($data);
        $useStandardDirectCharge = $this->applyConnectRouting($payload);

        try {
            $intent = $this->executePaymentIntentCreate($payload, $useStandardDirectCharge);

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

    /**
     * Build the base PaymentIntent payload, applying Stripe Tax when eligible.
     *
     * Phase-42 TAX-3: opt-in Stripe Tax for non-KES landlords who
     * have stripe_tax_enabled set on their PaymentConfiguration.
     * Stripe Tax doesn't yet cover Kenya — KES uses Phase-42
     * local VAT computation via StripeTaxService instead.
     */
    private function buildPaymentIntentPayload(array $data): array
    {
        $payload = [
            'amount' => (int) $data['amount'],
            'currency' => strtolower($data['currency']),
            'metadata' => array_merge($data['metadata'] ?? [], ['reference' => $data['reference']]),
            'receipt_email' => $data['receipt_email'] ?? null,
            'automatic_payment_methods' => ['enabled' => true],
        ];

        if ($this->config !== null
            && $this->config->hasStripeTaxEnabled()
            && strtolower($data['currency']) !== 'kes'
        ) {
            $payload['automatic_tax'] = ['enabled' => true];
        }

        return $payload;
    }

    /**
     * Apply Connect account routing to the payload in-place.
     *
     * Phase-42 CONNECT-STANDARD-2: route the PaymentIntent based on
     * the landlord's Connect account type. Express uses destination
     * charges (transfer_data.destination); Standard uses direct
     * charges (on_behalf_of) executed in the landlord's account
     * context via the Stripe-Account header. Untyped or missing
     * account_id stays the legacy direct-to-platform behaviour.
     *
     * Returns true when Standard direct-charge routing is active.
     */
    private function applyConnectRouting(array &$payload): bool
    {
        if ($this->config === null || empty($this->config->stripe_connect_account_id)) {
            return false;
        }

        $accountId = (string) $this->config->stripe_connect_account_id;
        $type = $this->config->stripe_connect_account_type instanceof \App\Enums\StripeConnectAccountType
            ? $this->config->stripe_connect_account_type
            : \App\Enums\StripeConnectAccountType::Express;

        if ($type === \App\Enums\StripeConnectAccountType::Standard) {
            $payload['on_behalf_of'] = $accountId;

            return true;
        }

        $payload['transfer_data'] = ['destination' => $accountId];

        return false;
    }

    /**
     * Execute the Stripe PaymentIntent create API call via the timed wrapper.
     *
     * Standard accounts charge in the landlord's account context —
     * the Stripe-Account header is the wire-level signal that routes
     * the API call appropriately.
     */
    private function executePaymentIntentCreate(array $payload, bool $useStandardDirectCharge): PaymentIntent
    {
        return $this->timedHttpRequest('stripe', '/v1/payment_intents', function () use ($payload, $useStandardDirectCharge) {
            if ($useStandardDirectCharge) {
                return $this->client()->paymentIntents->create($payload, [
                    'stripe_account' => $payload['on_behalf_of'],
                ]);
            }

            return $this->client()->paymentIntents->create($payload);
        });
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
            $page = $this->fetchChargePage($from, $to, $startingAfter);

            if ($page === null) {
                break;
            }

            foreach ($page->data as $charge) {
                $result[$charge->id] = $charge;
            }

            $startingAfter = $this->nextCursor($page);
        } while ($startingAfter !== null);

        return $result;
    }

    /**
     * Fetch a single page of Stripe charges within the time window.
     *
     * Returns null on API error (caller should break pagination loop).
     */
    private function fetchChargePage(CarbonImmutable $from, CarbonImmutable $to, ?string $startingAfter): mixed
    {
        $params = [
            'created' => ['gte' => $from->getTimestamp(), 'lte' => $to->getTimestamp()],
            'limit' => 100,
        ];

        if ($startingAfter !== null) {
            $params['starting_after'] = $startingAfter;
        }

        try {
            return $this->client()->charges->all($params);
        } catch (ApiErrorException $e) {
            Log::warning('stripe listCharges failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Derive the next pagination cursor from a Stripe list page.
     *
     * Returns null when there are no further pages.
     */
    private function nextCursor(mixed $page): ?string
    {
        $hasMore = (bool) ($page->has_more ?? false);

        if (! $hasMore || empty($page->data)) {
            return null;
        }

        return end($page->data)->id;
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

    /**
     * Phase-60 BILLING-PORTAL-1: create a Stripe Customer Portal
     * session for the landlord. Returns the hosted-portal URL the
     * caller can redirect to. Throws BillingPortalUnavailable when
     * the landlord has no StripeCustomer mapping (hasn't completed
     * Stripe onboarding) or when the SDK call fails — caller is
     * expected to flash an error in that case.
     *
     * @throws \App\Exceptions\BillingPortalUnavailable
     */
    public function createBillingPortalSession(\App\Models\User $landlord, string $returnUrl): string
    {
        $customer = \App\Models\StripeCustomer::query()
            ->where('user_id', $landlord->id)
            ->first();

        if (! $customer || ! $customer->stripe_customer_id) {
            throw new \App\Exceptions\BillingPortalUnavailable('billing.portal_not_provisioned');
        }

        if (! $this->isConfigured()) {
            throw new \App\Exceptions\BillingPortalUnavailable('billing.portal_gateway_not_configured');
        }

        try {
            $session = $this->client()->billingPortal->sessions->create([
                'customer' => $customer->stripe_customer_id,
                'return_url' => $returnUrl,
            ]);

            return (string) $session->url;
        } catch (\Throwable $e) {
            throw new \App\Exceptions\BillingPortalUnavailable('billing.portal_session_failed', previous: $e);
        }
    }
}
