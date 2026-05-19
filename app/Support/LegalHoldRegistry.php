<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\LegalHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-64 LEGAL-HOLD-1/2: static facade over the legal_holds table.
 * Authoritative for "is this subject under a preservation order?"
 * checks inside the Phase 63 messages:enforce-retention cron.
 *
 * Cache::remember 60s on heldIdsFor — bounds the cost of the array
 * lookup inside the retention chunked loop. Cache busts on hold +
 * release writes via a versioned cache key embedded with the count.
 */
class LegalHoldRegistry
{
    public const CACHE_TTL_SECONDS = 60;

    public static function hold(Model $subject, User $by, string $reason): LegalHold
    {
        $hold = LegalHold::create([
            'holdable_type' => $subject::class,
            'holdable_id' => $subject->getKey(),
            'reason' => $reason,
            'held_by' => $by->id,
            'held_at' => now(),
        ]);

        self::flushCacheFor($subject::class);

        return $hold;
    }

    public static function release(Model $subject, User $by): ?LegalHold
    {
        $hold = LegalHold::query()
            ->forSubject($subject::class, (int) $subject->getKey())
            ->active()
            ->first();

        if ($hold === null) {
            return null;
        }

        $hold->update([
            'released_at' => now(),
            'released_by' => $by->id,
        ]);

        self::flushCacheFor($subject::class);

        return $hold;
    }

    public static function isHeld(Model $subject): bool
    {
        return in_array(
            (int) $subject->getKey(),
            self::heldIdsFor($subject::class),
            true,
        );
    }

    /**
     * @return array<int, int>
     */
    public static function heldIdsFor(string $modelClass): array
    {
        return Cache::remember(
            self::cacheKey($modelClass),
            self::CACHE_TTL_SECONDS,
            static fn () => LegalHold::query()
                ->where('holdable_type', $modelClass)
                ->whereNull('released_at')
                ->pluck('holdable_id')
                ->map(static fn ($id) => (int) $id)
                ->all(),
        );
    }

    public static function activeCount(): int
    {
        return LegalHold::query()->whereNull('released_at')->count();
    }

    public static function flushCacheFor(string $modelClass): void
    {
        Cache::forget(self::cacheKey($modelClass));
    }

    private static function cacheKey(string $modelClass): string
    {
        return 'legal_hold:ids:'.str_replace('\\', '_', $modelClass);
    }
}
