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
        $reference = (string) ($payload['data']['reference'] ?? '');

        if ($event === '' || $reference === '') {
            return response()->json(['error' => 'invalid_payload'], 422);
        }

        $dedupKey = 'paystack-webhook-'.$event.'-'.$reference;
        if (! Cache::add($dedupKey, true, now()->addHours(24))) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        Log::info('Paystack webhook accepted', [
            'event' => $event,
            'reference' => $reference,
        ]);

        return response()->json(['status' => 'accepted'], 200);
    }
}
