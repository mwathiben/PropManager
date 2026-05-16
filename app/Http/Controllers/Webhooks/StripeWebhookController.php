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

        if (str_starts_with($type, 'customer.subscription.')) {
            $this->handleSubscriptionEvent($type, $event->data->object->toArray());
        }

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
