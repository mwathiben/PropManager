<?php

namespace App\Services\Payment;

use App\Models\WebhookLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookLogService
{
    private array $timers = [];

    public function recordHit(
        string $provider,
        string $eventId,
        string $eventType,
        string $rawPayload,
        ?int $landlordId = null,
        ?string $ipAddress = null
    ): WebhookLog {
        $payloadHash = hash('sha256', $rawPayload);
        $now = now();

        try {
            return WebhookLog::withoutGlobalScope('landlord')->create([
                'landlord_id' => $landlordId,
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload_hash' => $payloadHash,
                'retry_count' => 1,
                'first_received_at' => $now,
                'last_received_at' => $now,
                'status' => WebhookLog::STATUS_PENDING,
                'ip_address' => $ipAddress,
            ]);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] !== 1062) {
                throw $e;
            }

            return $this->incrementRetry($provider, $eventId, $payloadHash, $now, $ipAddress);
        }
    }

    public function startTiming(string $timerKey): void
    {
        $this->timers[$timerKey] = microtime(true);
    }

    public function finishTiming(WebhookLog $log, string $timerKey, string $status): void
    {
        $startTime = $this->timers[$timerKey] ?? null;
        $processingTimeMs = $startTime
            ? (int) round((microtime(true) - $startTime) * 1000)
            : 0;

        unset($this->timers[$timerKey]);

        if ($status === WebhookLog::STATUS_PROCESSED) {
            $log->markProcessed($processingTimeMs);
        } else {
            $log->markFailed($processingTimeMs);
        }
    }

    private function incrementRetry(
        string $provider,
        string $eventId,
        string $payloadHash,
        $now,
        ?string $ipAddress
    ): WebhookLog {
        WebhookLog::withoutGlobalScope('landlord')
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->update([
                'retry_count' => DB::raw('retry_count + 1'),
                'last_received_at' => $now,
                'payload_hash' => $payloadHash,
                'ip_address' => $ipAddress,
                'status' => WebhookLog::STATUS_PENDING,
            ]);

        $log = WebhookLog::withoutGlobalScope('landlord')
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($log->retry_count >= 3) {
            Log::warning('Webhook high retry count', [
                'provider' => $provider,
                'event_id' => $eventId,
                'retry_count' => $log->retry_count,
            ]);
        }

        return $log;
    }
}
