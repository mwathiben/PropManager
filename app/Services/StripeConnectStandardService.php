<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StripeConnectAccountType;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Phase-42 CONNECT-STANDARD-1: Stripe Connect Standard variant.
 * Sibling to Phase-41 StripeConnectService (Express) — Standard
 * accounts are owned by the landlord (their own dashboard login,
 * tax filing independence) and use direct charges (Stripe-Account
 * header + on_behalf_of) instead of destination charges.
 *
 * Shares the same system-wide platform credentials as Express.
 */
class StripeConnectStandardService
{
    protected string $secretKey;

    protected ?StripeClient $client = null;

    public function __construct()
    {
        $this->secretKey = (string) (\App\Models\Setting::getSystem('stripe_secret_key') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    protected function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'StripeConnectStandardService is not configured. Set system stripe_secret_key.'
            );
        }
        if ($this->client === null) {
            $this->client = new StripeClient($this->secretKey);
        }

        return $this->client;
    }

    /**
     * Standard accounts get card_payments + transfers requested up-front;
     * the landlord still needs to complete the hosted onboarding flow
     * to satisfy KYC and provide a payout bank account.
     */
    public function createStandardAccount(User $landlord, string $country = 'US'): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $account = $this->client()->accounts->create([
                'type' => 'standard',
                'country' => $country,
                'email' => $landlord->email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                    'card_payments' => ['requested' => true],
                ],
                'metadata' => [
                    'landlord_id' => $landlord->id,
                    'platform' => 'PropManager',
                    'connect_account_type' => StripeConnectAccountType::Standard->value,
                ],
            ]);

            $this->persistAccount($landlord, $account->id);

            return [
                'success' => true,
                'account_id' => $account->id,
                'status' => 'pending_onboarding',
                'type' => StripeConnectAccountType::Standard->value,
            ];
        } catch (ApiErrorException $e) {
            Log::warning('stripe createStandardAccount failed', [
                'landlord_id' => $landlord->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function onboardingLink(string $accountId, string $returnUrl, string $refreshUrl): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $link = $this->client()->accountLinks->create([
                'account' => $accountId,
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
                'type' => 'account_onboarding',
            ]);

            return $link->url;
        } catch (ApiErrorException $e) {
            Log::warning('stripe accountLinks->create (standard) failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncAccountStatus(string $accountId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $account = $this->client()->accounts->retrieve($accountId);
        } catch (ApiErrorException $e) {
            Log::warning('stripe accounts->retrieve (standard) failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Phase-42 follow-up: hash-keyed lookup (was a no-op WHERE
        // against the encrypted column before).
        $config = PaymentConfiguration::findByConnectAccountId($accountId);
        if ($config !== null) {
            $config->update([
                'stripe_connect_status' => $this->statusFor($account),
                'stripe_connect_charges_enabled' => (bool) $account->charges_enabled,
                'stripe_connect_payouts_enabled' => (bool) $account->payouts_enabled,
            ]);
        }

        return [
            'status' => $this->statusFor($account),
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) ($account->details_submitted ?? false),
            'type' => StripeConnectAccountType::Standard->value,
        ];
    }

    private function persistAccount(User $landlord, string $accountId): void
    {
        $config = PaymentConfiguration::query()->firstOrCreate(['landlord_id' => $landlord->id]);
        $config->update([
            'stripe_connect_account_id' => $accountId,
            'stripe_connect_account_type' => StripeConnectAccountType::Standard->value,
            'stripe_connect_status' => 'pending_onboarding',
            'stripe_connect_charges_enabled' => false,
            'stripe_connect_payouts_enabled' => false,
        ]);
    }

    private function statusFor(\Stripe\Account $account): string
    {
        return match (true) {
            (bool) $account->charges_enabled && (bool) $account->payouts_enabled => 'active',
            (bool) ($account->details_submitted ?? false) => 'pending_verification',
            default => 'pending_onboarding',
        };
    }
}
