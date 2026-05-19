<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\LegalHold;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-65 BULK-HOLD-1: multi-subject hold operations inside a
 * single DB::transaction with one cache bust per subject_type at end.
 * Caps at config('legal_hold.bulk_max', 100) with 500 hardcoded
 * ceiling to bound transaction time.
 */
class BulkHoldService
{
    public const HARDCODED_BULK_CEILING = 500;

    /**
     * @param  array<int, int>  $subjectIds
     * @return array<int, LegalHold>
     */
    public function holdAll(string $subjectClass, array $subjectIds, User $by, string $reason): array
    {
        $this->validateSubjectClass($subjectClass);
        $this->validateBulkSize($subjectIds);
        $this->validateOwnership($subjectClass, $subjectIds, (int) $by->id);

        $holds = DB::transaction(function () use ($subjectClass, $subjectIds, $by, $reason) {
            $now = now();

            return array_map(
                fn (int $id) => LegalHold::create([
                    'holdable_type' => $subjectClass,
                    'holdable_id' => $id,
                    'reason' => $reason,
                    'held_by' => $by->id,
                    'held_at' => $now,
                ]),
                $subjectIds,
            );
        });

        LegalHoldRegistry::flushCacheFor($subjectClass);

        return $holds;
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    public function releaseAll(string $subjectClass, array $subjectIds, User $by): int
    {
        $this->validateSubjectClass($subjectClass);
        $this->validateBulkSize($subjectIds);
        $this->validateOwnership($subjectClass, $subjectIds, (int) $by->id);

        $released = DB::transaction(fn () => LegalHold::query()
            ->where('holdable_type', $subjectClass)
            ->whereIn('holdable_id', $subjectIds)
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'released_by' => $by->id,
            ]));

        LegalHoldRegistry::flushCacheFor($subjectClass);

        return $released;
    }

    private function validateSubjectClass(string $subjectClass): void
    {
        if (! in_array($subjectClass, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true)) {
            throw new InvalidArgumentException('legal_hold.unsupported_holdable_type');
        }
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    private function validateBulkSize(array $subjectIds): void
    {
        if ($subjectIds === []) {
            throw new InvalidArgumentException('legal_hold.bulk_empty');
        }

        $cap = min((int) config('legal_hold.bulk_max', 100), self::HARDCODED_BULK_CEILING);

        if (count($subjectIds) > $cap) {
            throw new InvalidArgumentException('legal_hold.bulk_max_exceeded');
        }
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    private function validateOwnership(string $subjectClass, array $subjectIds, int $landlordId): void
    {
        $found = $subjectClass::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $subjectIds)
            ->where('landlord_id', $landlordId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($found) !== count(array_unique($subjectIds))) {
            throw new InvalidArgumentException('legal_hold.subject_not_owned');
        }
    }
}
