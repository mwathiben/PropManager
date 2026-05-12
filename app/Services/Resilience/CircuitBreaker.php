<?php

declare(strict_types=1);

namespace App\Services\Resilience;

use App\Exceptions\Resilience\CircuitOpenException;
use App\Services\MetricsService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Phase-16 RESIL-1: Cache-backed circuit breaker for external API calls.
 *
 * State machine:
 *   CLOSED      → normal traffic, count failures in a sliding window
 *   OPEN        → fast-fail with CircuitOpenException for cooldown_seconds
 *   HALF_OPEN   → allow exactly one probe; success → CLOSED, failure → OPEN
 *
 * Opt-in via per-provider `circuit_breaker.enabled` config. When disabled,
 * guard() passes through transparently. Tripping is governed by a failure
 * count in a window (failure_threshold), not by rate — simpler to reason
 * about and operationally observable.
 *
 * MetricsService increments:
 *   - circuit_breaker.opened{provider}        on every CLOSED → OPEN transition
 *   - circuit_breaker.short_circuited{provider} on every fast-fail
 *   - circuit_breaker.closed{provider}        on every HALF_OPEN → CLOSED transition
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Run a callable through the breaker.
     *
     * @template T
     *
     * @param  callable():T  $callable
     * @return T
     *
     * @throws CircuitOpenException When the breaker is OPEN and within cooldown.
     */
    public function guard(string $provider, string $endpoint, callable $callable): mixed
    {
        if (! $this->enabled($provider)) {
            return $callable();
        }

        $state = $this->state($provider, $endpoint);

        if ($state === self::STATE_OPEN) {
            $this->recordShortCircuited($provider);
            throw new CircuitOpenException($provider, $endpoint, $this->cooldownRemaining($provider, $endpoint));
        }

        try {
            $result = $callable();
            $this->recordSuccess($provider, $endpoint, $state);

            return $result;
        } catch (Throwable $e) {
            // CircuitOpenException is rethrown from inside the callable in the
            // unlikely case the callable itself short-circuits us; do not
            // count it as another failure or we'd open the breaker on its
            // own short-circuits.
            if ($e instanceof CircuitOpenException) {
                throw $e;
            }

            $this->recordFailure($provider, $endpoint);
            throw $e;
        }
    }

    public function state(string $provider, string $endpoint): string
    {
        $openUntil = $this->cache()->get($this->openKey($provider, $endpoint));
        if ($openUntil === null) {
            return self::STATE_CLOSED;
        }

        $now = now()->timestamp;
        if ((int) $openUntil <= $now) {
            return self::STATE_HALF_OPEN;
        }

        return self::STATE_OPEN;
    }

    public function reset(string $provider, string $endpoint): void
    {
        $this->cache()->forget($this->openKey($provider, $endpoint));
        $this->cache()->forget($this->failureKey($provider, $endpoint));
    }

    private function recordSuccess(string $provider, string $endpoint, string $priorState): void
    {
        $this->cache()->forget($this->failureKey($provider, $endpoint));

        if ($priorState === self::STATE_HALF_OPEN) {
            $this->cache()->forget($this->openKey($provider, $endpoint));
            $this->incrementMetric('circuit_breaker.closed', $provider);
        }
    }

    private function recordFailure(string $provider, string $endpoint): void
    {
        $key = $this->failureKey($provider, $endpoint);
        $window = $this->failureWindowSeconds($provider);

        // Cache::increment requires the key to exist; add() it on first
        // failure so the count starts from 0+1, with a TTL that bounds the
        // window. Subsequent failures within the window increment in place.
        if (! $this->cache()->add($key, 0, $window)) {
            // Key exists — just increment.
        }
        $count = (int) $this->cache()->increment($key);

        if ($count >= $this->failureThreshold($provider)) {
            $cooldown = $this->cooldownSeconds($provider);
            $this->cache()->put($this->openKey($provider, $endpoint), now()->timestamp + $cooldown, $cooldown + 60);
            $this->cache()->forget($key);
            $this->incrementMetric('circuit_breaker.opened', $provider);
        }
    }

    private function recordShortCircuited(string $provider): void
    {
        $this->incrementMetric('circuit_breaker.short_circuited', $provider);
    }

    private function cooldownRemaining(string $provider, string $endpoint): int
    {
        $openUntil = (int) $this->cache()->get($this->openKey($provider, $endpoint), 0);

        return max(0, $openUntil - now()->timestamp);
    }

    private function enabled(string $provider): bool
    {
        return (bool) config("services.{$provider}.circuit_breaker.enabled", false);
    }

    private function failureThreshold(string $provider): int
    {
        return (int) config("services.{$provider}.circuit_breaker.failure_threshold", 5);
    }

    private function failureWindowSeconds(string $provider): int
    {
        return (int) config("services.{$provider}.circuit_breaker.failure_window_seconds", 60);
    }

    private function cooldownSeconds(string $provider): int
    {
        return (int) config("services.{$provider}.circuit_breaker.cooldown_seconds", 30);
    }

    private function openKey(string $provider, string $endpoint): string
    {
        return "circuit_breaker:{$provider}:".sha1($endpoint).':open_until';
    }

    private function failureKey(string $provider, string $endpoint): string
    {
        return "circuit_breaker:{$provider}:".sha1($endpoint).':failures';
    }

    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    private function incrementMetric(string $name, string $provider): void
    {
        try {
            app(MetricsService::class)->increment($name, labels: ['provider' => $provider]);
        } catch (Throwable) {
            // Metrics is best-effort; never break the caller.
        }
    }
}
