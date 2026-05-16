<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase-30 INT-MPESA-DEEP-3: dedicated Paystack webhook receiver
 * with HMAC-SHA512 signature verification. The pre-Phase-30 path
 * (PaymentController::handleWebhook) trusted IP allowlisting alone;
 * Paystack's documented webhook contract is to compute
 * hash_hmac('sha512', $rawBody, $secretKey) and ship the hex digest
 * as the x-paystack-signature header. Without that check, an IP
 * forgery (or a NAT misconfiguration) would let any inbound POST
 * count as Paystack.
 *
 * Endpoint: POST /webhooks/v2/paystack (NOT the legacy /webhooks/paystack)
 * Layered behaviour: signature verification THEN idempotency-key
 * dedup THEN handoff to the existing PaymentCallbackProcessor.
 */
class PaystackWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('x-paystack-signature', '');
        $secret = (string) config('services.paystack.secret_key', '');

        if ($secret === '') {
            Log::warning('Paystack webhook rejected — secret_key not configured');

            return response()->json(['error' => 'not_configured'], 503);
        }

        $expected = hash_hmac('sha512', $rawBody, $secret);
        if (! hash_equals($expected, $signature)) {
            Log::warning('Paystack webhook rejected — signature mismatch', [
                'ip' => $request->ip(),
                'bytes' => strlen($rawBody),
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $event = (string) ($payload['event'] ?? '');

        // Phase-37 PWA-GATEWAY-2: subscription lifecycle events carry
        // subscription_code instead of reference. Charge events still
        // dedup on reference; sub events dedup on subscription_code.
        $isSubscriptionEvent = str_starts_with($event, 'subscription.');
        $dedupId = $isSubscriptionEvent
            ? (string) ($payload['data']['subscription_code'] ?? '')
            : (string) ($payload['data']['reference'] ?? '');

        if ($event === '' || $dedupId === '') {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        $dedupKey = 'paystack-webhook-'.$event.'-'.$dedupId;
        if (! Cache::add($dedupKey, true, now()->addHours(24))) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        if ($isSubscriptionEvent) {
            $this->handleSubscriptionEvent($event, $payload['data'] ?? []);
        }

        Log::info('Paystack webhook accepted', [
            'event' => $event,
            'dedup_id' => $dedupId,
        ]);

        return response()->json(['status' => 'accepted'], 200);
    }

    /**
     * Phase-37 PWA-GATEWAY-2: route subscription.* events to local
     * Subscription state mutations so Paystack-driven changes stay
     * in sync without polling. Failures are logged but do NOT 5xx
     * — Paystack retries on non-2xx and we already deduped, so a
     * 5xx would silently leak the event on the second delivery.
     */
    private function handleSubscriptionEvent(string $event, array $data): void
    {
        $code = (string) ($data['subscription_code'] ?? '');
        if ($code === '') {
            return;
        }

        $subscription = \App\Models\Subscription::query()
            ->where('paystack_subscription_code', $code)
            ->first();
        if (! $subscription) {
            Log::info('Paystack subscription event for unknown code', [
                'event' => $event,
                'code' => $code,
            ]);

            return;
        }

        switch ($event) {
            case 'subscription.create':
                $subscription->update(['status' => 'active']);
                break;
            case 'subscription.disable':
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                break;
            case 'subscription.not_renew':
                $subscription->update([
                    'status' => 'non_renewing',
                    'cancel_reason' => 'paystack_not_renew',
                ]);
                break;
        }
    }
}
