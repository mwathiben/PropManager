<?php

declare(strict_types=1);

namespace App\Services\Vendors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-39 VENDOR-ANALYTICS-1: PostHog implementation of
 * AnalyticsForwarderInterface. Posts batched events to
 * /batch endpoint (PostHog ingest API). Why PostHog as the
 * inaugural vendor: generous free tier, open-source, GDPR
 * posture matches PropManager's KE+EU customer mix.
 *
 * Retry semantics: counted as `retryable` when HTTP 5xx OR
 * Retry-After header present. Counted as `rejected` (no retry)
 * when 4xx OR invalid payload. AnalyticsReplayBatch advances
 * its last_replayed_at cursor only on full success — so
 * retryable failures cause the next run to re-attempt the same
 * window (idempotent on PostHog's side via per-event uuid).
 */
class PostHogForwarder implements AnalyticsForwarderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $host = 'https://app.posthog.com',
    ) {}

    public function vendor(): string
    {
        return 'posthog';
    }

    public function flush(array $events): array
    {
        if ($events === []) {
            return ['accepted' => 0, 'rejected' => 0, 'retryable' => 0, 'vendor' => 'posthog'];
        }

        $batch = [
            'api_key' => $this->apiKey,
            'batch' => array_map(
                fn (array $event) => [
                    'distinct_id' => $event['distinct_id'],
                    'event' => $event['event'],
                    'properties' => $event['properties'],
                    'timestamp' => $event['timestamp'],
                ],
                $events,
            ),
        ];

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($this->host.'/batch', $batch);

            if ($response->successful()) {
                return [
                    'accepted' => count($events),
                    'rejected' => 0,
                    'retryable' => 0,
                    'vendor' => 'posthog',
                ];
            }

            return $this->handleNon2xxResponse($response, $events);
        } catch (\Throwable $e) {
            return $this->handleTransportException($e, $events);
        }
    }

    private function handleNon2xxResponse(\Illuminate\Http\Client\Response $response, array $events): array
    {
        $status = $response->status();
        $retryable = $status >= 500 || $response->header('Retry-After') !== null;

        Log::warning('PostHog forwarder non-2xx', [
            'status' => $status,
            'retryable' => $retryable,
            'body' => $response->body(),
        ]);

        return [
            'accepted' => 0,
            'rejected' => $retryable ? 0 : count($events),
            'retryable' => $retryable ? count($events) : 0,
            'vendor' => 'posthog',
        ];
    }

    private function handleTransportException(\Throwable $e, array $events): array
    {
        Log::warning('PostHog forwarder threw', ['error' => $e->getMessage()]);

        return [
            'accepted' => 0,
            'rejected' => 0,
            'retryable' => count($events),
            'vendor' => 'posthog',
        ];
    }
}
