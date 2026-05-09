<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PropertyController extends Controller
{
    /**
     * Display all properties for the landlord (Home page).
     */
    public function index()
    {
        $user = auth()->user();

        $properties = $user->properties()
            ->with(['buildings' => function ($query) {
                $query->withCount('units')
                    ->withCount(['units as occupied_units_count' => function ($q) {
                        $q->where('status', 'occupied');
                    }]);
            }])
            ->get()
            ->map(function ($property) {
                $property->buildings->transform(function ($building) {
                    $building->occupancy_rate = $building->units_count > 0
                        ? round(($building->occupied_units_count / $building->units_count) * 100)
                        : 0;

                    return $building;
                });

                return $property;
            });

        return Inertia::render('Landlord/Home', [
            'properties' => $properties,
            'buildingTypes' => Building::BUILDING_TYPES,
        ]);
    }

    /**
     * Store a newly created property (and its first building).
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:residential,commercial,industrial,mixed',
            'address' => 'nullable|string|max:255',
            // Main Building Details
            'building_name' => 'required|string|max:255',
            'floors' => 'required|integer|min:1',
            'units_per_floor' => 'required|integer|min:1',
        ]);

        // PRIV-6: caretaker creating a property must persist the parent
        // landlord's id, not auth()->id() (which is the caretaker user_id).
        $actor = auth()->user();
        $landlordId = $actor->isCaretaker() ? (int) $actor->landlord_id : (int) $actor->id;

        return DB::transaction(function () use ($request, $landlordId) {
            // 1. Create Property
            $property = Property::create([
                'landlord_id' => $landlordId,
                'name' => $request->name,
                'type' => $request->type,
                'address' => $request->address,
            ]);

            // 2. Create the Main Building
            $building = Building::create([
                'property_id' => $property->id,
                'landlord_id' => $landlordId,
                'name' => $request->building_name,
                'total_floors' => $request->floors,
                'units_per_floor' => $request->units_per_floor,
                'building_type' => $request->type, // inherit type
            ]);

            // 3. Generate Units
            for ($f = 1; $f <= $request->floors; $f++) {
                for ($u = 1; $u <= $request->units_per_floor; $u++) {
                    $unitNumber = ($f * 100) + $u;
                    Unit::create([
                        'landlord_id' => $landlordId,
                        'building_id' => $building->id,
                        'floor_number' => $f,
                        'unit_number' => (string) $unitNumber,
                        'status' => 'vacant',
                        'target_rent' => 0, // Default
                    ]);
                }
            }

            return redirect()->route('dashboard');
        });
    }
}
