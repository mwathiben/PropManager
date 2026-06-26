<?php

namespace App\Http\Controllers;

use App\Http\Requests\Building\AddUnitRequest;
use App\Http\Requests\Building\StorePropertyBuildingRequest;
use App\Http\Requests\Building\StoreWingRequest;
use App\Http\Requests\Building\UpdateAutomationSettingsRequest;
use App\Http\Requests\Building\UpdateBuildingSettingsRequest;
use App\Http\Requests\Building\UpdateBuildingWaterSettingsRequest;
use App\Http\Requests\Building\UpdateUnitsRequest;
use App\Http\Requests\StoreBuildingRequest;
use App\Models\Building;
use App\Models\Unit;
use App\Services\Building\AmenityDetailService;
use App\Services\BuildingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService,
        protected AmenityDetailService $amenityDetails,
    ) {}

    public function index(Request $request)
    {
        $buildingGroups = $this->buildingService->getFilteredBuildings(auth()->id(), $request);

        return Inertia::render('Buildings/Index', [
            'buildingGroups' => $buildingGroups,
            'buildingTypes' => Building::BUILDING_TYPES,
            'amenityOptions' => Building::AMENITY_OPTIONS,
            'filters' => [
                'search' => $request->search,
                'type' => $request->type,
                'sort' => $request->get('sort', 'name_asc'),
            ],
        ]);
    }

    public function storeStandalone(StoreBuildingRequest $request)
    {
        $building = $this->buildingService->createStandaloneBuilding($request->validated(), auth()->id());

        return redirect()->route('buildings.show', $building)
            ->with('success', 'Building created successfully.');
    }

    public function show(Building $building)
    {
        $redirect = $this->redirectIfHasWings($building, 'buildings.show');
        if ($redirect) {
            return $redirect;
        }

        $this->authorizeBuilding($building);

        $data = $this->buildingService->getBuildingDetails($building);

        return Inertia::render('Buildings/Show', $data);
    }

    public function dashboard(Building $building, Request $request)
    {
        $redirect = $this->redirectIfHasWings($building, 'buildings.dashboard');
        if ($redirect) {
            return $redirect;
        }

        $this->authorizeBuilding($building);

        $data = $this->buildingService->getBuildingDashboardData($building, $request);

        return Inertia::render('Buildings/Dashboard', $data);
    }

    private function redirectIfHasWings(Building $building, string $routeName): ?\Illuminate\Http\RedirectResponse
    {
        if (! $building->hasWings()) {
            return null;
        }

        $firstWing = $building->wings()->orderBy('name')->first();

        return $firstWing ? redirect()->route($routeName, $firstWing) : null;
    }

    private function authorizeBuilding(Building $building): void
    {
        $user = auth()->user();

        if ($user->isScopeOwner() && $building->landlord_id !== $user->id) {
            abort(403);
        }

        if ($user->isCaretaker() && $building->landlord_id !== $user->landlord_id) {
            abort(403);
        }
    }

    public function updateSettings(UpdateBuildingSettingsRequest $request, Building $building)
    {
        $building->update($request->validated());
        $this->buildingService->syncSharedSettings($building);
        // Phase-78 AMENITY-DEPTH-2: persist per-amenity detail after the
        // amenities write so the sync sees the current selection.
        $this->amenityDetails->sync($building->refresh(), $request->validated()['amenity_details'] ?? []);

        return redirect()->back()->with('success', 'Building settings updated.');
    }

    public function store(StorePropertyBuildingRequest $request, $propertyId)
    {
        $this->buildingService->createBuilding($propertyId, $request->validated(), auth()->id());

        return redirect()->back();
    }

    public function storeWing(StoreWingRequest $request, Building $building)
    {
        $validated = $request->validated();
        $prefix = strtoupper($validated['unit_prefix']);
        $existingPrefixes = $building->wings()->pluck('unit_prefix')->toArray();

        if (in_array($prefix, $existingPrefixes)) {
            return redirect()->back()->withErrors(['unit_prefix' => 'This prefix is already in use by another wing.']);
        }

        $wing = $this->buildingService->createWing($building, $validated, auth()->id());

        return redirect()->back()->with('success', "Wing '{$wing->name}' added successfully.");
    }

    public function edit(Building $building)
    {
        if ($building->hasWings()) {
            $firstWing = $building->wings()->orderBy('name')->first();
            if ($firstWing) {
                return redirect()->route('buildings.edit', $firstWing);
            }
        }

        $units = $building->units()
            ->orderBy('floor_number', 'desc')
            ->orderBy('unit_number', 'asc')
            ->get();

        $siblingBuildings = Building::where('property_id', $building->property_id)
            ->where(function ($q) {
                $q->where('is_wing', true)
                    ->orWhereDoesntHave('wings');
            })
            ->get();

        $building->load('property:id,name,address');

        $parentBuilding = $building->isWing() ? $building->parentBuilding : null;

        return Inertia::render('Buildings/Edit', [
            'building' => $building,
            'units' => $units,
            'buildings' => $siblingBuildings,
            'amenityOptions' => Building::AMENITY_OPTIONS,
            // Phase-78 AMENITY-DEPTH-3: per-amenity detail keyed for the editor.
            'amenityDetails' => $building->amenityDetails()->get()->mapWithKeys(fn ($d) => [$d->amenity_key => [
                'quantity' => $d->quantity,
                'provider' => $d->provider,
                'account_ref' => $d->account_ref,
                'monthly_cost' => $d->monthly_cost,
            ]]),
            'parentBuilding' => $parentBuilding ? [
                'id' => $parentBuilding->id,
                'name' => $parentBuilding->name,
            ] : null,
        ]);
    }

    public function updateUnits(UpdateUnitsRequest $request, Building $building)
    {
        $validated = $request->validated();

        try {
            $this->buildingService->bulkUpdateUnits(
                $building,
                $validated['selectedUnitIds'],
                $validated['action'],
                $validated['value'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['units' => $e->getMessage()]);
        }

        return redirect()->back();
    }

    public function addUnit(AddUnitRequest $request, Building $building)
    {
        $validated = $request->validated();

        $exists = Unit::where('building_id', $building->id)
            ->where('unit_number', (string) $validated['unit_number'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['unit_number' => 'Unit number already exists']);
        }

        $this->buildingService->addUnit($building, $validated, auth()->id());

        return redirect()->back();
    }

    public function waterSettings(Building $building)
    {
        // Phase-83 follow-up WATER-SETTINGS-UNIFY: there is now ONE canonical
        // water-settings editor (global defaults + per-building overrides). The
        // old standalone per-building page is folded in — redirect to the unified
        // editor anchored to this building's override row.
        return redirect()->route('water.settings', ['building' => $building->id]);
    }

    public function updateWaterSettings(UpdateBuildingWaterSettingsRequest $request, Building $building)
    {
        $validated = $request->validated();
        $billingType = $validated['water_billing_type'] ?? null;

        $building->update([
            'water_billing_type' => $billingType,
            'water_flat_rate' => $billingType === 'flat_rate'
                ? ($validated['water_flat_rate'] ?? null)
                : null,
            'water_unit_rate' => $billingType === 'consumption'
                ? ($validated['water_unit_rate'] ?? null)
                : null,
        ]);

        $building->refresh();
        $this->buildingService->syncSharedSettings($building);

        // Phase-79 WATER-GATE: this is a primary enable/disable point for the
        // conditional water module — bust the access cache.
        \App\Services\Water\WaterModuleAccess::forget((int) $building->landlord_id);

        return redirect()->back()->with('success', 'Water settings updated successfully.');
    }

    public function updateAutomationSettings(UpdateAutomationSettingsRequest $request, Building $building)
    {
        $building->update($request->validated());
        $this->buildingService->syncSharedSettings($building);

        return redirect()->back()->with('success', 'Invoice automation settings updated.');
    }

    public function destroy(Building $building)
    {
        $this->authorize('delete', $building);

        $buildingName = $building->name;

        try {
            $this->buildingService->deleteBuilding($building);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['building' => $e->getMessage()]);
        }

        return redirect()->route('buildings.index')
            ->with('success', "Building '{$buildingName}' has been deleted.");
    }
}
