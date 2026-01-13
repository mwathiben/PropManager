<?php

namespace App\Http\Controllers;

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

    public function updateSettings(Request $request, Building $building)
    {
        if ($building->landlord_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'building_type' => 'required|string|in:'.implode(',', array_keys(Building::BUILDING_TYPES)),
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:2000',
            'amenities' => 'nullable|array',
            'amenities.selected' => 'nullable|array',
            'amenities.custom' => 'nullable|array',
            'coordinates' => 'nullable|array',
            'coordinates.lat' => 'nullable|numeric|between:-90,90',
            'coordinates.lng' => 'nullable|numeric|between:-180,180',
            'photos' => 'nullable|array',
        ]);

        $building->update($validated);

        return redirect()->back()->with('success', 'Building settings updated.');
    }

    public function store(Request $request, $propertyId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'floors' => 'required|integer|min:1',
            'units_per_floor' => 'required|integer|min:1',
        ]);

        $this->buildingService->createBuilding($propertyId, $validated, auth()->id());

        return redirect()->back();
    }

    public function storeWing(Request $request, Building $building)
    {
        if ($building->landlord_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit_prefix' => 'required|string|max:3',
            'floors' => 'required|integer|min:1|max:100',
            'units_per_floor' => 'required|integer|min:1|max:50',
        ]);

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

    public function updateUnits(Request $request, Building $building)
    {
        $request->validate([
            'selectedUnitIds' => 'required|array',
            'action' => 'required|string',
            'value' => 'nullable',
        ]);

        $this->buildingService->bulkUpdateUnits(
            $building,
            $request->selectedUnitIds,
            $request->action,
            $request->value
        );

        return redirect()->back();
    }

    public function addUnit(Request $request, Building $building)
    {
        $validated = $request->validate([
            'floor_number' => 'required',
            'unit_number' => 'required',
            'target_rent' => 'required|numeric',
            'unit_type' => 'required|string',
        ]);

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

    public function updateWaterSettings(Request $request, Building $building)
    {
        $request->validate([
            'water_billing_type' => 'nullable|in:consumption,flat_rate',
            'water_flat_rate' => 'nullable|numeric|min:0|required_if:water_billing_type,flat_rate',
        ]);

        $building->update([
            'water_billing_type' => $request->water_billing_type,
            'water_flat_rate' => $request->water_billing_type === 'flat_rate'
                ? $request->water_flat_rate
                : null,
        ]);

        return redirect()->back()->with('success', 'Water settings updated successfully.');
    }

    public function updateAutomationSettings(Request $request, Building $building)
    {
        if ($building->landlord_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'auto_generate_invoices' => 'boolean',
            'invoice_generation_day' => 'required_if:auto_generate_invoices,true|integer|min:1|max:28',
            'auto_send_invoices' => 'boolean',
        ]);

        $building->update($validated);

        return redirect()->back()->with('success', 'Invoice automation settings updated.');
    }
}
