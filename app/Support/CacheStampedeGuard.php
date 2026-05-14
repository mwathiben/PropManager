<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-22 PERF-CACHE-3: stampede-protected Cache::remember.
 *
 * When a hot cache key expires under concurrency, a bare
 * Cache::remember lets every in-flight request miss simultaneously and
 * all of them run the (expensive) compute — a thundering herd. At
 * month-start, many dashboard loads hit an expired finance:stats:{id}
 * key at once; that is exactly this failure mode.
 *
 * remember() is a drop-in replacement for Cache::remember with the
 * same ($key, $ttl, $callback) signature: on a miss the first caller
 * acquires a short lock and computes; concurrent missers block briefly
 * for that compute then read the freshly-set value. A caller that
 * cannot acquire the lock within the wait window falls back to
 * computing directly — it NEVER blocks indefinitely, so the worst case
 * is the pre-Phase-22 behaviour (everyone computes), never worse.
 */
final class CacheStampedeGuard
{
    public static function remember(
        string $key,
        int $ttl,
        callable $callback,
        int $lockSeconds = 10,
        int $waitSeconds = 3,
    ): mixed {
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock("stampede:{$key}", $lockSeconds);

        try {
            // block() throws LockTimeoutException if the lock can't be
            // acquired within $waitSeconds.
            $lock->block($waitSeconds);

            // Acquired — but a waiter ahead of us may have just
            // populated the key while we blocked.
            $cached = Cache::get($key);
            if ($cached !== null) {
                return $cached;
            }

            $value = $callback();
            Cache::put($key, $value, $ttl);

            return $value;
        } catch (LockTimeoutException) {
            // Couldn't get the lock in time — compute directly rather
            // than block the request. Degrades to the herd, never hangs.
            $value = $callback();
            Cache::put($key, $value, $ttl);

            return $value;
        } finally {
            // Safe even if block() threw — release() no-ops when the
            // lock isn't held by this owner.
            $lock->release();
        }
    }
}
