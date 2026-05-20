<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Phase-64 LEGAL-HOLD-1/2: static facade over the legal_holds table.
 * Phase-65 MORPH-EXPAND-1: ALLOWED_HOLDABLE_TYPES allow-list guards
 * against arbitrary morph-type injection (defense-in-depth — a
 * malicious POST tampering subject_type to App\Models\User could
 * otherwise mint stealth holds on arbitrary tables).
 *
 * Cache::remember 60s on heldIdsFor — bounds the cost of the array
 * lookup inside the retention chunked loop. Cache busts on hold +
 * release writes via a versioned cache key embedded with the count.
 */
class LegalHoldRegistry
{
    public const CACHE_TTL_SECONDS = 60;

    public const ALLOWED_HOLDABLE_TYPES = [
        MessageThread::class,
        Document::class,
        Invoice::class,
        Ticket::class,
    ];

    public static function hold(Model $subject, User $by, string $reason): LegalHold
    {
        if (! in_array($subject::class, self::ALLOWED_HOLDABLE_TYPES, true)) {
            throw new InvalidArgumentException('legal_hold.unsupported_holdable_type');
        }

        // Idempotent: one active hold per subject. MySQL allows duplicate
        // released_at=NULL rows past the unique index, so a double-submit
        // would otherwise mint a second active hold.
        $existing = LegalHold::query()
            ->forSubject($subject::class, (int) $subject->getKey())
            ->active()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

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

    /**
     * Phase-65 HOLD-UI-3: count active holds across every allowed
     * subject for a specific landlord. Driven by the Inertia share
     * for the sidebar badge.
     */
    public static function activeCountForLandlord(int $landlordId): int
    {
        $total = 0;

        foreach (self::ALLOWED_HOLDABLE_TYPES as $subjectClass) {
            $heldIds = self::heldIdsFor($subjectClass);

            if ($heldIds === []) {
                continue;
            }

            $total += $subjectClass::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $heldIds)
                ->where('landlord_id', $landlordId)
                ->count();
        }

        return $total;
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
