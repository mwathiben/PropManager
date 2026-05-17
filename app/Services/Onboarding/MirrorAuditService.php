<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase-46 CANONICAL-AUDIT-1: scans every users.* column registered in
 * config('onboarding.mirrors') and counts the rows where the mirror has
 * drifted from the canonical source. Designed to be cheap-enough to run
 * daily (one indexed SELECT per registered mirror).
 *
 * Drift is detected with a NULL-safe inequality: NULL ≠ NULL is treated
 * as match (both empty), but NULL vs 'value' is treated as drift (one
 * side wrote, the other didn't).
 */
class MirrorAuditService
{
    /**
     * @return Collection<int, array{
     *   mirror: string,
     *   canonical: string,
     *   pinned: bool,
     *   drift_count: int,
     * }>
     */
    public function scan(): Collection
    {
        $mirrors = (array) config('onboarding.mirrors', []);
        $results = collect();

        foreach ($mirrors as $entry) {
            $results->push($this->scanOne($entry));
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{mirror: string, canonical: string, pinned: bool, drift_count: int}
     */
    public function scanOne(array $entry): array
    {
        [$mirrorTable, $mirrorCol] = explode('.', $entry['column']);
        [$canonicalTable, $canonicalCol] = explode('.', $entry['canonical']);
        [$keyTable, $keyCol] = explode('.', $entry['key']);

        $query = DB::table($mirrorTable)
            ->join($canonicalTable, "{$mirrorTable}.id", '=', "{$keyTable}.{$keyCol}");

        // Optional WHERE clause filtering the canonical row (e.g.,
        // emergency_contacts.is_primary = true).
        foreach ((array) ($entry['canonical_filter'] ?? []) as $col => $value) {
            $query->where("{$canonicalTable}.{$col}", $value);
        }

        // Role-scope: only scan users with the matching role.
        if (! empty($entry['role_scope'])) {
            $query->whereIn("{$mirrorTable}.role", (array) $entry['role_scope']);
        }

        // NULL-safe drift count: <=> in MySQL (the null-safe equal)
        // negated. NULL <=> NULL = 1 (no drift); 'x' <=> NULL = 0 (drift).
        $driftCount = (int) $query
            ->whereRaw("NOT ({$mirrorTable}.{$mirrorCol} <=> {$canonicalTable}.{$canonicalCol})")
            ->count();

        return [
            'mirror' => $entry['column'],
            'canonical' => $entry['canonical'],
            'pinned' => (bool) ($entry['pinned'] ?? false),
            'drift_count' => $driftCount,
        ];
    }
}
