<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Part;
use App\Models\PartSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase-75 PARTS-PRICING-2: manage the suppliers (vendors) that stock a part.
 * Part is route-bound (TenantScope 404s a foreign part); the vendor must belong
 * to the same landlord.
 */
class PartSupplierController extends Controller
{
    use WithLandlordScope;

    /** Cap unit cost well within the unsignedBigInteger cents column (~KES 1B). */
    private const MAX_CENTS = 100_000_000_000;

    public function store(Request $request, Part $part): RedirectResponse
    {
        $landlordId = $this->getLandlordId();
        abort_unless((int) $part->landlord_id === $landlordId, 404);

        $data = $request->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('landlord_id', $landlordId)],
            'unit_cost_cents' => ['required', 'integer', 'min:0', 'max:'.self::MAX_CENTS],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_order_qty' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        PartSupplier::updateOrCreate(
            ['part_id' => $part->id, 'vendor_id' => (int) $data['vendor_id']],
            [
                'landlord_id' => $landlordId,
                'unit_cost_cents' => (int) $data['unit_cost_cents'],
                'lead_time_days' => (int) $data['lead_time_days'],
                'min_order_qty' => (int) $data['min_order_qty'],
            ],
        );

        return back()->with('success', __('parts.pricing.flash.supplier_saved'));
    }

    public function destroy(Request $request, Part $part, PartSupplier $supplier): RedirectResponse
    {
        $landlordId = $this->getLandlordId();
        abort_unless(
            (int) $part->landlord_id === $landlordId && (int) $supplier->part_id === (int) $part->id,
            404,
        );

        $supplier->delete();

        return back()->with('success', __('parts.pricing.flash.supplier_removed'));
    }
}
