<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-25 API-WEBHOOK-1 + WEBHOOK-2: dispatch a webhook payload to
 * a landlord-registered subscription URL with HMAC-SHA256 signing.
 *
 * Retry policy: 5 attempts total with exponential backoff (15s, 60s,
 * 300s, 1800s — same shape as the Phase-16 RESIL-8 inbound
 * WebhookDeadLetter pattern). After the 5th failure the delivery row
 * gets dead_lettered=true and the job stops retrying — operator can
 * manually retry from the UI (which creates a new delivery row).
 *
 * Every attempt writes a WebhookDelivery row so the landlord UI can
 * surface the full audit trail. The dispatch is fail-open at the
 * Laravel level — a non-2xx response is logged but does not throw,
 * since throwing would mark the queued job as failed and Laravel's
 * default retry would conflict with our explicit retry-count logic.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry delays in seconds keyed by attempt number (1-indexed).
     * Attempt 5 has no follow-up — dead-letter terminates the chain.
     */
    private const BACKOFF_SECONDS = [
        1 => 15,
        2 => 60,
        3 => 300,
        4 => 1800,
    ];

    public function __construct(
        public int $subscriptionId,
        public string $eventType,
        /** @var array<string, mixed> */
        public array $payload,
        public int $attempt = 1,
    ) {
        $this->onQueue('payments');
    }

    public function handle(): void
    {
        /** @var WebhookSubscription|null $subscription */
        $subscription = WebhookSubscription::query()
            ->withoutGlobalScope('landlord')
            ->find($this->subscriptionId);

        if (! $subscription || ! $subscription->active) {
            return;
        }

        $delivery = WebhookDelivery::create([
            'webhook_subscription_id' => $this->subscriptionId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'attempt' => $this->attempt,
            'dispatched_at' => now(),
        ]);

        $body = json_encode([
            'event' => $this->eventType,
            'data' => $this->payload,
            'delivery_id' => $delivery->id,
            'dispatched_at' => $delivery->dispatched_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $body, $subscription->secret);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'PropManager-Webhook/1.0',
                    'X-PropManager-Event' => $this->eventType,
                    'X-PropManager-Signature' => 'sha256='.$signature,
                    'X-PropManager-Delivery-Id' => (string) $delivery->id,
                    'X-PropManager-Attempt' => (string) $this->attempt,
                ])
                ->withBody($body, 'application/json')
                ->post($subscription->url);

            $delivery->update([
                'http_status' => $response->status(),
                'response_body' => mb_substr((string) $response->body(), 0, 2000),
                'completed_at' => now(),
            ]);

            if ($response->successful()) {
                $subscription->forceFill(['last_delivery_at' => now()])->saveQuietly();

                return;
            }

            $this->scheduleRetryOrDeadLetter($delivery, "HTTP {$response->status()}");
        } catch (ConnectionException $e) {
            $delivery->update([
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            $this->scheduleRetryOrDeadLetter($delivery, 'connection_failed');
        } catch (\Throwable $e) {
            Log::error('Webhook delivery threw unexpectedly', [
                'subscription_id' => $this->subscriptionId,
                'event' => $this->eventType,
                'attempt' => $this->attempt,
                'error' => $e->getMessage(),
            ]);
            $delivery->update([
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            $this->scheduleRetryOrDeadLetter($delivery, 'exception');
        }
    }

    private function scheduleRetryOrDeadLetter(WebhookDelivery $delivery, string $reason): void
    {
        if ($this->attempt >= WebhookDelivery::MAX_ATTEMPTS) {
            $delivery->update(['dead_lettered' => true]);
            Log::warning('Webhook delivery dead-lettered', [
                'delivery_id' => $delivery->id,
                'subscription_id' => $this->subscriptionId,
                'event' => $this->eventType,
                'attempt' => $this->attempt,
                'reason' => $reason,
            ]);

            return;
        }

        $nextDelay = self::BACKOFF_SECONDS[$this->attempt] ?? 1800;

        self::dispatch($this->subscriptionId, $this->eventType, $this->payload, $this->attempt + 1)
            ->delay(now()->addSeconds($nextDelay));
    }
}
