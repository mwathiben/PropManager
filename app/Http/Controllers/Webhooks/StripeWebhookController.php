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

        // Stripe SDK normally hydrates $event->data->object as a
        // \Stripe\StripeObject with ->toArray(); when the request body
        // sends `data.object` as a literal PHP array (test pings, some
        // edge events), it stays an array. Either shape is acceptable.
        $dataObject = $event->data->object ?? null;
        $payload = is_object($dataObject) && method_exists($dataObject, 'toArray')
            ? $dataObject->toArray()
            : (array) ($dataObject ?? []);

        match (true) {
            str_starts_with($type, 'customer.subscription.') => $this->handleSubscriptionEvent($type, $payload),
            $type === 'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($payload),
            $type === 'charge.refunded' => $this->handleChargeRefunded($payload),
            $type === 'invoice.payment_failed' => $this->handleInvoicePaymentFailed($payload),
            $type === 'charge.dispute.created' => $this->handleChargeDisputeCreated($payload),
            $type === 'charge.dispute.closed' => $this->handleChargeDisputeClosed($payload),
            $type === 'account.updated' => $this->handleAccountUpdated($payload),
            $type === 'price.updated' => $this->handlePriceUpdated($payload),
            $type === 'customer.created' => $this->handleCustomerCreated($payload),
            $type === 'customer.updated' => $this->handleCustomerUpdated($payload),
            $type === 'customer.deleted' => $this->handleCustomerDeleted($payload),
            $type === 'payout.failed' => $this->handlePayoutFailed($payload),
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
        $disputeId = (string) ($payload['id'] ?? '');

        // Ops-facing incident (unchanged) — fires regardless of attribution.
        \App\Models\OperationalIncident::create([
            'severity' => 'sev3',
            'title' => 'Stripe dispute on charge '.$chargeId,
            'status' => 'open',
            'opened_at' => now(),
            'affected_services' => ['stripe', 'payments'],
            'summary' => sprintf(
                'Dispute %s on charge %s — amount=%s %s, reason=%s',
                $disputeId,
                $chargeId,
                (string) ($payload['amount'] ?? ''),
                strtoupper((string) ($payload['currency'] ?? '')),
                (string) ($payload['reason'] ?? ''),
            ),
        ]);

        // Phase-85 DISPUTE-1: first-class landlord-facing record, attributed via
        // the Payment behind the disputed intent. No auto-reversal (can be won).
        if ($disputeId === '') {
            return;
        }

        $intentId = (string) ($payload['payment_intent'] ?? '');
        $payment = $intentId !== ''
            ? \App\Models\Payment::query()->where('paystack_reference', $intentId)->first()
            : null;

        if (! $payment) {
            Log::info('Stripe dispute not attributed to a rent payment', ['dispute' => $disputeId, 'charge' => $chargeId]);

            return;
        }

        $dispute = \App\Models\PaymentDispute::updateOrCreate(
            ['gateway_dispute_id' => $disputeId],
            [
                'payment_id' => $payment->id,
                'landlord_id' => $payment->landlord_id,
                'gateway' => 'stripe',
                'charge_reference' => $chargeId,
                'amount' => ((int) ($payload['amount'] ?? 0)) / 100,
                'currency' => strtoupper((string) ($payload['currency'] ?? 'usd')),
                'reason' => (string) ($payload['reason'] ?? '') ?: null,
                'status' => \App\Models\PaymentDispute::STATUS_OPEN,
                'opened_at' => now(),
                'raw' => $payload,
            ],
        );

        if ($dispute->wasRecentlyCreated) {
            $this->notifyLandlordOfDispute($dispute);
        }
    }

    /**
     * Phase-85 DISPUTE-3: dispute resolved (won/lost). Updates the record only —
     * a "lost" dispute does NOT auto-reverse the payment here (operator decision).
     */
    private function handleChargeDisputeClosed(array $payload): void
    {
        $disputeId = (string) ($payload['id'] ?? '');
        if ($disputeId === '') {
            return;
        }

        $dispute = \App\Models\PaymentDispute::query()->where('gateway_dispute_id', $disputeId)->first();
        if (! $dispute) {
            return;
        }

        $status = match ((string) ($payload['status'] ?? '')) {
            'won' => \App\Models\PaymentDispute::STATUS_WON,
            'lost' => \App\Models\PaymentDispute::STATUS_LOST,
            default => \App\Models\PaymentDispute::STATUS_CLOSED,
        };

        $dispute->update(['status' => $status, 'resolved_at' => now(), 'raw' => $payload]);
    }

    private function notifyLandlordOfDispute(\App\Models\PaymentDispute $dispute): void
    {
        try {
            app(\App\Services\NotificationService::class)->send(
                (int) $dispute->landlord_id,
                \App\Models\Notification::TYPE_PAYMENT_DISPUTE,
                __('payment_dispute.notify_subject'),
                __('payment_dispute.notify_body', [
                    'amount' => number_format((float) $dispute->amount, 2),
                    'currency' => $dispute->currency,
                ]),
                ['dispute_id' => $dispute->id, 'url' => route('gateway-reconciliation.index')],
                (int) $dispute->landlord_id,
            );
        } catch (\Throwable $e) {
            Log::warning('dispute landlord notification failed', ['dispute' => $dispute->id, 'error' => $e->getMessage()]);
        }
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

    /**
     * Phase-42 PAYOUT-AUDIT-2: real-time signal when a Stripe Connect
     * payout fails. Increments stripe_payout_failure_count gauge
     * immediately + opens a sev3 OperationalIncident so on-call can
     * investigate the landlord's bank account / Connect status. The
     * twice-daily cron (PAYOUT-AUDIT-1) catches anything this webhook
     * drops.
     */
    private function handlePayoutFailed(array $payload): void
    {
        $payoutId = (string) ($payload['id'] ?? '');
        $failureMessage = (string) ($payload['failure_message'] ?? 'unknown');
        $destinationAccountId = (string) ($payload['destination'] ?? '');

        // Phase-42 follow-up: hash-keyed reverse lookup. Replaces the
        // O(n) decrypted-scan workaround Phase 42 Phase 1f shipped.
        $landlordId = 0;
        if ($destinationAccountId !== '') {
            $config = \App\Models\PaymentConfiguration::findByConnectAccountId($destinationAccountId);
            $landlordId = (int) ($config?->landlord_id ?? 0);
        }

        app(\App\Services\MetricsService::class)->gauge('stripe_payout_failure_count', 1, [
            'landlord_id' => (string) $landlordId,
            'source' => 'webhook',
        ]);

        \App\Models\OperationalIncident::create([
            'title' => sprintf('Stripe payout failed — payout_id=%s', $payoutId),
            'severity' => \App\Models\OperationalIncident::SEV3,
            'status' => \App\Models\OperationalIncident::STATUS_OPEN,
            'opened_at' => \Illuminate\Support\Carbon::now(),
            'affected_services' => ['stripe', 'payouts'],
            'summary' => sprintf(
                'Stripe payout.failed webhook: payout %s on Connect account %s failed (landlord %d). failure_message: %s',
                $payoutId,
                $destinationAccountId,
                $landlordId,
                $failureMessage,
            ),
        ]);
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
