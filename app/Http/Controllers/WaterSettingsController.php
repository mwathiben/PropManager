<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterSetting\UpdateWaterSettingsRequest;
use App\Models\Building;
use App\Models\PaymentConfiguration;
use App\Services\Water\WaterSettingsData;
use Inertia\Inertia;

class WaterSettingsController extends Controller
{
    /**
     * Display the water settings page.
     */
    public function index()
    {
        $user = auth()->user();

        // Phase-86 ROLE-SPLIT: water billing configuration is landlord-only.
        // Caretakers record/review readings but do not set rates or billing type.
        if (! $user->isLandlord()) {
            abort(403, 'Access denied.');
        }

        $landlordId = (int) $user->id;

        // Phase-83 follow-up WATER-SETTINGS-UNIFY: one canonical payload (shared
        // with the Water hub Settings tab) so both surfaces show identical data.
        return Inertia::render('Water/Settings', array_merge(
            WaterSettingsData::forLandlord($landlordId),
            // Optional deep-link target when arriving from a building's page.
            ['highlightBuildingId' => request()->integer('building') ?: null],
        ));
    }

    /**
     * Update the water settings.
     */
    public function update(UpdateWaterSettingsRequest $request)
    {
        $validated = $request->validated();

        // Phase-86 ROLE-SPLIT: UpdateWaterSettingsRequest::authorize() restricts
        // this to landlords, so the configured landlord is always the actor.
        $landlordId = (int) auth()->id();

        $defaultRate = config('propmanager.water.default_rate', 150);

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $landlordId],
            [
                'water_billing_type' => $validated['water_billing_type'],
                'water_unit_rate' => $validated['water_unit_rate'] ?? $defaultRate,
                'flat_water_rate' => $validated['flat_water_rate'] ?? 0,
            ]
        );

        if (! empty($validated['building_overrides'])) {
            foreach ($validated['building_overrides'] as $override) {
                $building = Building::where('id', $override['id'])
                    ->where('landlord_id', $landlordId)
                    ->first();

                if ($building) {
                    $billingType = $override['water_billing_type'] ?? 'inherit';

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

        // Phase-79 WATER-GATE: water_billing_type may have just flipped the
        // module on/off — bust the access cache so nav/guards re-resolve.
        \App\Services\Water\WaterModuleAccess::forget($landlordId);

        return redirect()->back()->with('success', 'Water settings updated successfully.');
    }
}
