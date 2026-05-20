<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\LegalHoldActiveException;
use App\Models\LegalHold;
use App\Support\LegalHoldRegistry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Phase-65 MORPH-EXPAND-2: shared legal-hold concern for any
 * model registered in LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES.
 * Single import per model exposes the relation + isHeld helper.
 *
 * Phase-68 HOLD-GUARD-1: the boot hook installs a deleting observer
 * that aborts the delete of a held subject on EVERY path (manual
 * delete, soft-delete, cascade) — the retention cron exclusion alone
 * left the manual-delete paths able to destroy preserved data.
 */
trait HasLegalHolds
{
    public static function bootHasLegalHolds(): void
    {
        static::deleting(function ($model): void {
            if ($model->isHoldable() && LegalHoldRegistry::isHeld($model)) {
                throw new LegalHoldActiveException($model::class, (int) $model->getKey());
            }
        });
    }

    public function legalHolds(): MorphMany
    {
        return $this->morphMany(LegalHold::class, 'holdable');
    }

    public function isHeld(): bool
    {
        return LegalHoldRegistry::isHeld($this);
    }

    public function isHoldable(): bool
    {
        return in_array(static::class, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES, true);
    }
}
