<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Part;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-75 PARTS-PRICING-3: landlord-facing parts pricing surface — per-part
 * price-history trend + a supplier comparison table (unit cost, lead time,
 * min order). Supplier rows are mutated through PartSupplierController.
 */
class PartPricingController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();

        $parts = Part::query()
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->with([
                'priceHistory' => fn ($q) => $q->limit(12),
                'suppliers.vendor:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Part $part) {
                $suppliers = $part->suppliers
                    ->sortBy('unit_cost_cents')
                    ->values();
                $cheapestId = $suppliers->first()?->id;
                $fastestId = $suppliers->sortBy('lead_time_days')->first()?->id;

                return [
                    'id' => $part->id,
                    'name' => $part->name,
                    'sku' => $part->sku,
                    'category' => $part->category,
                    'cost_per_unit_cents' => $part->cost_per_unit_cents,
                    'qty_available' => $part->qty_available,
                    'reorder_threshold' => $part->reorder_threshold,
                    'price_history' => $part->priceHistory->map(fn ($row) => [
                        'cost_per_unit_cents' => $row->cost_per_unit_cents,
                        'source' => $row->source,
                        'effective_at' => $row->effective_at?->toIso8601String(),
                    ])->values(),
                    'suppliers' => $suppliers->map(fn ($s) => [
                        'id' => $s->id,
                        'vendor_id' => $s->vendor_id,
                        'vendor_name' => $s->vendor?->name,
                        'unit_cost_cents' => $s->unit_cost_cents,
                        'lead_time_days' => $s->lead_time_days,
                        'min_order_qty' => $s->min_order_qty,
                        'is_cheapest' => $s->id === $cheapestId,
                        'is_fastest' => $s->id === $fastestId,
                    ])->values(),
                ];
            });

        $vendors = Vendor::query()
            ->where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendor $v) => ['id' => $v->id, 'name' => $v->name]);

        return Inertia::render('Parts/Pricing', [
            'parts' => $parts,
            'vendors' => $vendors,
        ]);
    }
}
