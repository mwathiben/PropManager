<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\SlaDefinition;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-49 SLA-PER-CATEGORY-2: cascade resolver for SLA definitions.
 *
 * Match order, most specific → least specific:
 *   1. landlord + category + subcategory + priority
 *   2. landlord + category + priority
 *   3. landlord + priority (any category)
 *   4. global  + category + subcategory + priority
 *   5. global  + category + priority
 *   6. global  + priority (any category)
 *   7. fallback to Ticket::SLA_SECONDS / RESOLUTION_SLA_SECONDS constants
 *
 * Cached 5 minutes per resolution tuple — TicketObserver::creating hits
 * this for every new ticket so the hot path stays under a couple of ms.
 */
class SlaDefinitionService
{
    public function resolveFor(string $category, ?string $subcategory, string $priority, ?int $landlordId): array
    {
        // Phase-54 SLA-LANDLORD-UI-3: include a per-landlord version
        // stamp in the cache key so SlaDefinitionObserver::flushCache
        // can invalidate every tuple-key for that landlord by
        // incrementing the version once — without scanning Redis or
        // relying on cache tags (the default file/database cache has
        // neither). 'global' is its own scope.
        $version = $this->versionFor($landlordId);

        $cacheKey = sprintf(
            'sla:resolve:v%d:%s:%s:%s:%s',
            $version,
            $landlordId ?? 'global',
            $category,
            $subcategory ?? 'any',
            $priority,
        );

        return Cache::remember($cacheKey, 300, function () use ($category, $subcategory, $priority, $landlordId): array {
            $row = $this->matchRow($category, $subcategory, $priority, $landlordId);

            if ($row !== null) {
                return [
                    'response_seconds' => $row->response_seconds,
                    'resolution_seconds' => $row->resolution_seconds,
                ];
            }

            return [
                'response_seconds' => Ticket::SLA_SECONDS[$priority] ?? Ticket::SLA_SECONDS['medium'],
                'resolution_seconds' => Ticket::RESOLUTION_SLA_SECONDS[$priority] ?? Ticket::RESOLUTION_SLA_SECONDS['medium'],
            ];
        });
    }

    /**
     * Phase-54 SLA-LANDLORD-UI-3: increment the per-landlord version
     * stamp so subsequent resolveFor() calls compute a fresh key. Pass
     * null to flush the global scope after a super-admin edit.
     */
    public function flushCache(?int $landlordId): void
    {
        $key = $this->versionKey($landlordId);
        // Cache::increment lazily creates the counter at 1 if missing,
        // so we set an initial value before incrementing for the first
        // time. The TTL is intentionally long — the version key itself
        // is tiny and bumps every write.
        Cache::add($key, 1, now()->addDays(365));
        Cache::increment($key);
    }

    private function versionFor(?int $landlordId): int
    {
        return (int) Cache::get($this->versionKey($landlordId), 1);
    }

    private function versionKey(?int $landlordId): string
    {
        return 'sla:ver:'.($landlordId ?? 'global');
    }

    private function matchRow(string $category, ?string $subcategory, string $priority, ?int $landlordId): ?SlaDefinition
    {
        $tries = [];

        if ($landlordId !== null) {
            $tries[] = ['landlord_id' => $landlordId, 'category' => $category, 'subcategory' => $subcategory, 'priority' => $priority];
            $tries[] = ['landlord_id' => $landlordId, 'category' => $category, 'subcategory' => null, 'priority' => $priority];
            $tries[] = ['landlord_id' => $landlordId, 'category' => null, 'subcategory' => null, 'priority' => $priority];
        }

        $tries[] = ['landlord_id' => null, 'category' => $category, 'subcategory' => $subcategory, 'priority' => $priority];
        $tries[] = ['landlord_id' => null, 'category' => $category, 'subcategory' => null, 'priority' => $priority];
        $tries[] = ['landlord_id' => null, 'category' => null, 'subcategory' => null, 'priority' => $priority];

        foreach ($tries as $criteria) {
            $query = SlaDefinition::query()->active();
            foreach ($criteria as $column => $value) {
                $query = $value === null
                    ? $query->whereNull($column)
                    : $query->where($column, $value);
            }
            $row = $query->first();
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }
}
