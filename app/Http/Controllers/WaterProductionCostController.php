<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Water\StoreWaterProductionCostRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\WaterProductionCost;

/**
 * Phase-91 PRODUCTION-COST: landlord-only capture of borehole running costs that
 * feed the cost-of-production-vs-revenue margin on the water intelligence tab.
 */
class WaterProductionCostController extends Controller
{
    use WithLandlordScope;

    public function store(StoreWaterProductionCostRequest $request)
    {
        WaterProductionCost::create([
            'landlord_id' => $this->getLandlordId(),
            'building_id' => $request->input('building_id'),
            'cost_date' => $request->input('cost_date'),
            'amount' => $request->input('amount'),
            'category' => $request->input('category'),
            'note' => $request->input('note'),
        ]);

        return back()->with('success', __('water.intelligence.cost_added'));
    }

    public function destroy(WaterProductionCost $productionCost)
    {
        $this->authorize('delete', $productionCost);

        $productionCost->delete();

        return back()->with('success', __('water.intelligence.cost_deleted'));
    }
}
