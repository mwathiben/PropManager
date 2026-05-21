<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Part;
use App\Models\PartPriceHistory;
use Illuminate\Support\Facades\Auth;

/**
 * Phase-75 PARTS-PRICING-1: append a price-history row when a part is created
 * and whenever its unit cost changes, so cost trend + audit are reconstructable.
 */
class PartObserver
{
    public function created(Part $part): void
    {
        $this->record($part);
    }

    public function updated(Part $part): void
    {
        if ($part->wasChanged('cost_per_unit_cents')) {
            $this->record($part);
        }
    }

    private function record(Part $part): void
    {
        PartPriceHistory::create([
            'part_id' => $part->id,
            'landlord_id' => $part->landlord_id,
            'cost_per_unit_cents' => $part->cost_per_unit_cents,
            'source' => PartPriceHistory::SOURCE_MANUAL,
            'effective_at' => now(),
            'recorded_by' => Auth::id(),
        ]);
    }
}
