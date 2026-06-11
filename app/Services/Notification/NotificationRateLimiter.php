<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-landlord, per-channel notification rate limiting, extracted from
 * NotificationService (M2 decomposition). Wraps the hourly/daily
 * RateLimiter buckets keyed by landlord + channel; the limits come from
 * the landlord's notification config. Behaviour is locked by
 * tests/Unit/Services/NotificationServiceTest (rate-limit cases) — a
 * verbatim move.
 */
class NotificationRateLimiter
{
    public function __construct(
        private readonly NotificationConfigRepositoryInterface $configRepository,
    ) {}

    public function check(int $landlordId, string $channel): bool
    {
        if ($channel === 'in_app') {
            return true;
        }

        $hourlyKey = "notifications:{$landlordId}:{$channel}:hourly";
        $dailyKey = "notifications:{$landlordId}:{$channel}:daily";

        $rateLimits = $this->configRepository->getRateLimits($landlordId);
        $hourlyLimit = $rateLimits['hourly'];
        $dailyLimit = $rateLimits['daily'];

        $hourlyAttempts = RateLimiter::attempt(
            $hourlyKey,
            $hourlyLimit,
            fn () => true,
            3600
        );

        if (! $hourlyAttempts) {
            return false;
        }

        $dailyAttempts = RateLimiter::attempt(
            $dailyKey,
            $dailyLimit,
            fn () => true,
            86400
        );

        return $dailyAttempts;
    }

    /**
     * Get remaining rate limit for a channel.
     *
     * @return array{hourly: array{remaining: int, limit: int, resets_at: int}, daily: array{remaining: int, limit: int, resets_at: int}}
     */
    public function remaining(int $landlordId, string $channel): array
    {
        $hourlyKey = "notifications:{$landlordId}:{$channel}:hourly";
        $dailyKey = "notifications:{$landlordId}:{$channel}:daily";

        $rateLimits = $this->configRepository->getRateLimits($landlordId);
        $hourlyLimit = $rateLimits['hourly'];
        $dailyLimit = $rateLimits['daily'];

        return [
            'hourly' => [
                'remaining' => max(0, $hourlyLimit - RateLimiter::attempts($hourlyKey)),
                'limit' => $hourlyLimit,
                'resets_at' => RateLimiter::availableAt($hourlyKey),
            ],
            'daily' => [
                'remaining' => max(0, $dailyLimit - RateLimiter::attempts($dailyKey)),
                'limit' => $dailyLimit,
                'resets_at' => RateLimiter::availableAt($dailyKey),
            ],
        ];
    }

    /**
     * Reset rate limits for a landlord (all channels, or one).
     */
    public function reset(int $landlordId, ?string $channel = null): void
    {
        $channels = $channel ? [$channel] : ['email', 'sms', 'whatsapp', 'push'];

        foreach ($channels as $ch) {
            RateLimiter::clear("notifications:{$landlordId}:{$ch}:hourly");
            RateLimiter::clear("notifications:{$landlordId}:{$ch}:daily");
        }
    }
}
