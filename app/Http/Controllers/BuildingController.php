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
use App\Services\BuildingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    public function __construct(
        protected BuildingService $buildingService
    ) {}

    public function index(Request $request)
    {
        $buildings = $this->buildingService->getFilteredBuildings(auth()->id(), $request);

        return Inertia::render('Buildings/Index', [
            'buildings' => $buildings,
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
        $user = auth()->user();
        if ($user->isLandlord() && $building->landlord_id !== $user->id) {
            abort(403);
        }
        if ($user->isCaretaker() && $building->landlord_id !== $user->landlord_id) {
            abort(403);
        }

        $data = $this->buildingService->getBuildingDetails($building);

        return Inertia::render('Buildings/Show', $data);
    }

    public function dashboard(Building $building, Request $request)
    {
        $user = auth()->user();
        if ($user->isLandlord() && $building->landlord_id !== $user->id) {
            abort(403);
        }
        if ($user->isCaretaker() && $building->landlord_id !== $user->landlord_id) {
            abort(403);
        }

        $data = $this->buildingService->getBuildingDashboardData($building, $request);

        return Inertia::render('Buildings/Dashboard', $data);
    }

    public function updateSettings(UpdateBuildingSettingsRequest $request, Building $building)
    {
        $building->update($request->validated());

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
        $units = $building->units()
            ->orderBy('floor_number', 'desc')
            ->orderBy('unit_number', 'asc')
            ->get();

        $siblingBuildings = Building::where('property_id', $building->property_id)->get();
        $building->load('property:id,name,address');

        return Inertia::render('Buildings/Edit', [
            'building' => $building,
            'units' => $units,
            'buildings' => $siblingBuildings,
            'amenityOptions' => Building::AMENITY_OPTIONS,
        ]);
    }

    public function updateUnits(UpdateUnitsRequest $request, Building $building)
    {
        $validated = $request->validated();

        $this->buildingService->bulkUpdateUnits(
            $building,
            $validated['selectedUnitIds'],
            $validated['action'],
            $validated['value'] ?? null
        );

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
        return Inertia::render('Buildings/WaterSettings', [
            'building' => $building,
        ]);
    }

    public function updateWaterSettings(UpdateBuildingWaterSettingsRequest $request, Building $building)
    {
        $validated = $request->validated();

        $building->update([
            'water_billing_type' => $validated['water_billing_type'] ?? null,
            'water_flat_rate' => ($validated['water_billing_type'] ?? null) === 'flat_rate'
                ? ($validated['water_flat_rate'] ?? null)
                : null,
        ]);

        return redirect()->back()->with('success', 'Water settings updated successfully.');
    }

    public function updateAutomationSettings(UpdateAutomationSettingsRequest $request, Building $building)
    {
        $building->update($request->validated());

        return redirect()->back()->with('success', 'Invoice automation settings updated.');
    }
}
