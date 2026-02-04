<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    private const DEFAULT_TTL_HOURS = 24;

    /**
     * Attempt to acquire an idempotency lock for the given key.
     *
     * Returns an array with:
     * - ['acquired' => true] if the lock was acquired (caller should process)
     * - ['acquired' => false, 'response' => array] if completed (return cached response)
     * - ['acquired' => false, 'response' => null, 'status' => string] if in progress
     */
    public function acquire(string $key, ?string $requestHash = null): array
    {
        return DB::transaction(function () use ($key, $requestHash) {
            $existing = IdempotencyKey::where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->isExpired()) {
                    $existing->delete();
                } elseif ($existing->isCompleted()) {
                    return [
                        'acquired' => false,
                        'response' => $existing->response_data,
                    ];
                } else {
                    return [
                        'acquired' => false,
                        'response' => null,
                        'status' => $existing->status,
                    ];
                }
            }

            IdempotencyKey::create([
                'key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ]);

            return ['acquired' => true];
        });
    }

    /**
     * Release the idempotency lock and store the response for future duplicate requests.
     */
    public function release(string $key, array $response): void
    {
        IdempotencyKey::where('key', $key)->update([
            'status' => 'completed',
            'response_data' => $response,
        ]);
    }

    /**
     * Mark the idempotency key as failed with an optional reason.
     */
    public function fail(string $key, ?string $reason = null): void
    {
        IdempotencyKey::where('key', $key)->update([
            'status' => 'failed',
            'response_data' => ['error' => $reason],
        ]);
    }

    /**
     * Check if a key is currently being processed (pending or processing status).
     */
    public function isProcessing(string $key): bool
    {
        return IdempotencyKey::where('key', $key)
            ->active()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    /**
     * Remove all expired idempotency keys.
     *
     * @return int Number of deleted keys
     */
    public function cleanupExpired(): int
    {
        return IdempotencyKey::expired()->delete();
    }

    /**
     * Generate a provider-prefixed idempotency key.
     */
    public static function generateKey(string $provider, string $reference): string
    {
        return $provider.':'.$reference;
    }
}
