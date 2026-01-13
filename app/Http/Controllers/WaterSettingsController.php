<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\PaymentConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WaterSettingsController extends Controller
{
    /**
     * Display the water settings page.
     */
    public function index()
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        // Get all buildings with their water settings
        $buildings = Building::where('landlord_id', $landlordId)
            ->select('id', 'name', 'water_billing_type', 'water_flat_rate', 'water_unit_rate')
            ->withCount('units')
            ->orderBy('name')
            ->get();

        // Get global payment configuration
        $paymentConfig = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        return Inertia::render('Water/Settings', [
            'buildings' => $buildings,
            'globalSettings' => [
                'water_billing_type' => $paymentConfig->water_billing_type ?? 'consumption',
                'water_unit_rate' => $paymentConfig->water_unit_rate ?? 150,
                'flat_water_rate' => $paymentConfig->flat_water_rate ?? 0,
            ],
        ]);
    }

    /**
     * Update the water settings.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord()) {
            abort(403, 'Only landlords can update water settings.');
        }

        $validated = $request->validate([
            'water_billing_type' => 'required|in:consumption,flat_rate,none',
            'water_unit_rate' => 'nullable|numeric|min:0',
            'flat_water_rate' => 'nullable|numeric|min:0',
            'building_overrides' => 'nullable|array',
            'building_overrides.*.id' => 'required|exists:buildings,id',
            'building_overrides.*.water_billing_type' => 'nullable|in:consumption,flat_rate,none,inherit',
            'building_overrides.*.water_unit_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_flat_rate' => 'nullable|numeric|min:0',
        ]);

        // Update global settings
        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $user->id],
            [
                'water_billing_type' => $validated['water_billing_type'],
                'water_unit_rate' => $validated['water_unit_rate'] ?? 150,
                'flat_water_rate' => $validated['flat_water_rate'] ?? 0,
            ]
        );

        // Update per-building overrides
        if (! empty($validated['building_overrides'])) {
            foreach ($validated['building_overrides'] as $override) {
                $building = Building::where('id', $override['id'])
                    ->where('landlord_id', $user->id)
                    ->first();

                if ($building) {
                    $billingType = $override['water_billing_type'] ?? 'inherit';

                    // If set to inherit, clear the building-specific settings
                    if ($billingType === 'inherit') {
                        $building->update([
                            'water_billing_type' => null,
                            'water_unit_rate' => null,
                            'water_flat_rate' => null,
                        ]);
                    } else {
                        $building->update([
                            'water_billing_type' => $billingType,
                            'water_unit_rate' => $override['water_unit_rate'] ?? null,
                            'water_flat_rate' => $override['water_flat_rate'] ?? null,
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Water settings updated successfully.');
    }
}
