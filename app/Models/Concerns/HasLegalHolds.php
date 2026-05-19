<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\LegalHold;
use App\Support\LegalHoldRegistry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Phase-65 MORPH-EXPAND-2: shared legal-hold concern for any
 * model registered in LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES.
 * Single import per model exposes the relation + isHeld helper.
 */
trait HasLegalHolds
{
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
