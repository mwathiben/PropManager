<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Phase-40 GATEWAY-WEBHOOK-1/2/3: Stripe webhook receiver. Mirrors
 * PaystackWebhookController structure: signature verification THEN
 * 24h dedup THEN dispatch lifecycle events to local Subscription
 * state. System-wide credentials (Setting::getSystem) — landlord-
 * scoped webhooks would multiply the route surface; defer per-tenant
 * webhook splitting until at least one landlord requests it.
 *
 * Endpoint: POST /webhooks/v2/stripe
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret', '');

        if ($secret === '') {
            Log::warning('Stripe webhook rejected — stripe_webhook_secret not configured');

            return response()->json(['error' => 'not_configured'], 503);
        }

        try {
            $event = Webhook::constructEvent($rawBody, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook rejected — signature mismatch', [
                'ip' => $request->ip(),
                'bytes' => strlen($rawBody),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        $eventId = (string) ($event->id ?? '');
        $type = (string) ($event->type ?? '');

        if ($eventId === '' || $type === '') {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        $dedupKey = 'stripe-webhook-'.$type.'-'.$eventId;
        if (! Cache::add($dedupKey, true, now()->addHours(24))) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        $payload = $event->data->object->toArray();

        match (true) {
            str_starts_with($type, 'customer.subscription.') => $this->handleSubscriptionEvent($type, $payload),
            $type === 'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($payload),
            $type === 'charge.refunded' => $this->handleChargeRefunded($payload),
            $type === 'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
            $type === 'charge.dispute.created' => $this->handleChargeDisputeCreated($payload),
            $type === 'account.updated' => $this->handleAccountUpdated($payload),
            $type === 'price.updated' => $this->handlePriceUpdated($payload),
            $type === 'customer.created' => $this->handleCustomerCreated($payload),
            $type === 'customer.updated' => $this->handleCustomerUpdated($payload),
            $type === 'customer.deleted' => $this->handleCustomerDeleted($payload),
            default => null,
        };

        Log::info('Stripe webhook accepted', [
            'type' => $type,
            'event_id' => $eventId,
        ]);

        return response()->json(['status' => 'accepted'], 200);
    }

    /**
     * Stripe customer.subscription.* events drive local Subscription
     * state mutations. Failures are logged, not raised — Stripe
     * retries on non-2xx and we already deduped so a 5xx would leak
     * the event on the next delivery.
     */
    private function handleSubscriptionEvent(string $type, array $data): void
    {
        $code = (string) ($data['id'] ?? '');
        if ($code === '') {
            return;
        }

        $subscription = \App\Models\Subscription::query()
            ->where('stripe_subscription_code', $code)
            ->first();
        if (! $subscription) {
            Log::info('Stripe subscription event for unknown code', [
                'type' => $type,
                'code' => $code,
            ]);

            return;
        }

        match ($type) {
            'customer.subscription.created' => $subscription->update([
                'status' => 'active',
            ]),
            'customer.subscription.updated' => $subscription->update([
                'status' => $this->mapStripeStatus((string) ($data['status'] ?? '')),
            ]),
            'customer.subscription.deleted' => $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]),
            default => null,
        };
    }

    /**
     * Phase-41 GATEWAY-WEBHOOK-DEEP-1: create Payment row when Stripe
     * confirms a PaymentIntent succeeded. Idempotent via the dedup key
     * in the parent handler — a duplicate webhook delivery returns
     * 'duplicate' before getting here.
     */
    private function handlePaymentIntentSucceeded(array $payload): void
    {
        $intentId = (string) ($payload['id'] ?? '');
        if ($intentId === '') {
            return;
        }

        $existing = \App\Models\Payment::query()
            ->where('paystack_reference', $intentId)
            ->first();
        if ($existing) {
            return;
        }

        $metadata = $payload['metadata'] ?? [];
        $landlordId = (int) ($metadata['landlord_id'] ?? 0);
        $leaseId = (int) ($metadata['lease_id'] ?? 0);
        $invoiceId = (int) ($metadata['invoice_id'] ?? 0);

        // SaaS-billing intents carry no landlord_id/lease_id metadata —
        // those are handled by customer.subscription.* events. Tenant
        // rent-collection intents MUST include both; if missing, log
        // and skip so the row never violates the lease_id FK.
        if ($landlordId === 0 || $leaseId === 0) {
            Log::info('Stripe payment_intent.succeeded missing rent-collection metadata', [
                'intent' => $intentId,
                'landlord_id' => $landlordId,
                'lease_id' => $leaseId,
            ]);

            return;
        }

        \App\Models\Payment::create([
            'invoice_id' => $invoiceId ?: null,
            'lease_id' => $leaseId,
            'landlord_id' => $landlordId,
            'amount' => (int) ($payload['amount'] ?? 0) / 100,
            'currency' => strtoupper((string) ($payload['currency'] ?? 'usd')),
            'payment_method' => 'stripe',
            'payment_date' => now(),
            'reference' => $intentId,
            'paystack_reference' => $intentId,
            'notes' => 'Stripe payment_intent.succeeded webhook',
        ]);
    }

    /**
     * Phase-41 GATEWAY-WEBHOOK-DEEP-2: flip Payment.is_voided when Stripe
     * refund posts (dispute reversal, customer-service goodwill).
     */
    private function handleChargeRefunded(array $payload): void
    {
        $intentId = (string) ($payload['payment_intent'] ?? '');
        if ($intentId === '') {
            return;
        }

        $payment = \App\Models\Payment::query()
            ->where('paystack_reference', $intentId)
            ->first();
        if (! $payment) {
            return;
        }

        $payment->update([
            'is_voided' => true,
            'voided_at' => now(),
            'void_reason' => 'stripe_refund',
        ]);

        \App\Events\PaymentRefundedExternal::dispatch($payment, 'stripe');
    }

    /**
     * Phase-41 GATEWAY-WEBHOOK-DEEP-3: flip Subscription.status to
     * past_due so Phase-34 dunning-emails cron picks it up.
     */
    private function handleInvoicePaymentFailed(array $payload): void
    {
        $subscriptionCode = (string) ($payload['subscription'] ?? '');
        if ($subscriptionCode === '') {
            return;
        }

        \App\Models\Subscription::query()
            ->where('stripe_subscription_code', $subscriptionCode)
            ->update([
                'status' => 'past_due',
            ]);
    }

    /**
     * Phase-41 GATEWAY-WEBHOOK-DEEP-4: log Stripe disputes to
     * operational_incidents so they surface on the ops dashboard.
     */
    private function handleChargeDisputeCreated(array $payload): void
    {
        $chargeId = (string) ($payload['charge'] ?? $payload['id'] ?? '');

        \App\Models\OperationalIncident::create([
            'severity' => 'sev3',
            'title' => 'Stripe dispute on charge '.$chargeId,
            'status' => 'open',
            'opened_at' => now(),
            'affected_services' => ['stripe', 'payments'],
            'summary' => sprintf(
                'Dispute %s on charge %s — amount=%s %s, reason=%s',
                (string) ($payload['id'] ?? ''),
                $chargeId,
                (string) ($payload['amount'] ?? ''),
                strtoupper((string) ($payload['currency'] ?? '')),
                (string) ($payload['reason'] ?? ''),
            ),
        ]);
    }

    /**
     * Phase-41 GATEWAY-CONNECT-3: refresh PaymentConfiguration's
     * Connect-status columns when Stripe posts account state changes
     * (KYC review outcome, capabilities granted/lost, etc.).
     */
    private function handleAccountUpdated(array $payload): void
    {
        $accountId = (string) ($payload['id'] ?? '');
        if ($accountId === '') {
            return;
        }
        app(\App\Services\StripeConnectService::class)->syncAccountStatus($accountId);
    }

    /**
     * Phase-41 GATEWAY-PLAN-SYNC-2/3 + Phase-42 PLAN-SYNC-AUTO-1/2:
     * detect Stripe-side Price edits diverging from local
     * SubscriptionPlan.price_monthly, emit subscription_plan_drift
     * gauge, append a row to subscription_plan_drift_log, then
     * delegate to PlanDriftResolver which branches on the plan's
     * drift_resolve_mode (manual_review default | always_app_wins
     * | always_stripe_wins).
     */
    private function handlePriceUpdated(array $payload): void
    {
        $priceId = (string) ($payload['id'] ?? '');
        $unitAmount = (int) ($payload['unit_amount'] ?? 0);
        if ($priceId === '') {
            return;
        }

        $plan = \App\Models\SubscriptionPlan::query()
            ->where('stripe_plan_code', $priceId)
            ->first();
        if (! $plan) {
            return;
        }

        $stripeMajor = $unitAmount / 100;
        $appMajor = (float) $plan->price_monthly;
        $delta = abs($stripeMajor - $appMajor);

        if ($delta > 0.01) {
            app(\App\Services\MetricsService::class)->gauge('subscription_plan_drift', $delta, [
                'plan_id' => (string) $plan->id,
            ]);
            Log::warning('Stripe Price drift detected', [
                'plan_id' => $plan->id,
                'app_price' => $appMajor,
                'stripe_price' => $stripeMajor,
                'delta' => $delta,
            ]);

            app(\App\Services\Subscriptions\PlanDriftResolver::class)
                ->resolve($plan, $unitAmount, $priceId);
        }
    }

    /**
     * Phase-42 METHODS-2: keep stripe_customers in sync with
     * Stripe-side mutations (operator created via Dashboard,
     * tenant deleted PII directly in Stripe, etc.).
     */
    private function handleCustomerCreated(array $payload): void
    {
        $customerId = (string) ($payload['id'] ?? '');
        $userId = (int) ($payload['metadata']['user_id'] ?? 0);
        if ($customerId === '' || $userId === 0) {
            return;
        }
        \App\Models\StripeCustomer::query()->updateOrCreate(
            ['user_id' => $userId],
            ['stripe_customer_id' => $customerId],
        );
    }

    private function handleCustomerUpdated(array $payload): void
    {
        $customerId = (string) ($payload['id'] ?? '');
        if ($customerId === '') {
            return;
        }
        $defaultPm = $payload['invoice_settings']['default_payment_method'] ?? null;
        \App\Models\StripeCustomer::query()
            ->where('stripe_customer_id', $customerId)
            ->update(['default_payment_method_id' => $defaultPm]);
    }

    private function handleCustomerDeleted(array $payload): void
    {
        $customerId = (string) ($payload['id'] ?? '');
        if ($customerId === '') {
            return;
        }
        // Soft-delete preserves the audit trail (Phase-13 DPA-3
        // 7-year retention) while preventing further use.
        \App\Models\StripeCustomer::query()
            ->where('stripe_customer_id', $customerId)
            ->delete();
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled', 'unpaid', 'incomplete_expired' => 'cancelled',
            'incomplete' => 'paused',
            default => 'active',
        };
    }
}
