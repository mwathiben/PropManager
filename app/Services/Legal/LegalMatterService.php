<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Phase-72 MATTER-GROUPING: matter-level operations. Releasing a matter frees
 * every active hold linked to it in one UPDATE (keyed on legal_matter_id, so it
 * isn't bound by the per-subject bulk cap), then busts the registry cache for
 * each affected subject type.
 */
class LegalMatterService
{
    public function release(LegalMatter $matter, User $by): int
    {
        $types = $matter->activeHolds()->distinct()->pluck('holdable_type')->all();

        if ($types === []) {
            return 0;
        }

        $released = DB::transaction(fn () => LegalHold::query()
            ->where('legal_matter_id', $matter->id)
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'released_by' => $by->id,
            ]));

        foreach ($types as $type) {
            LegalHoldRegistry::flushCacheFor($type);
        }

        return $released;
    }

    /** A matter can only be closed once every hold under it is released. */
    public function canClose(LegalMatter $matter): bool
    {
        return $matter->activeHolds()->doesntExist();
    }
}
