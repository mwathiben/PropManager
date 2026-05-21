<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Property\PropertyMetricsService;
use App\Services\Reports\NoiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    use WithLandlordScope;

    public function __construct(
        protected PropertyMetricsService $metrics,
    ) {}

    /**
     * Phase-78 PROPERTY-VIEW-3: the property tier — every property with its
     * portfolio metrics (occupancy / rent roll / arrears).
     */
    public function index(): Response
    {
        return Inertia::render('Properties/Index', [
            'properties' => $this->metrics->forLandlord($this->getLandlordId()),
        ]);
    }

    /**
     * Phase-78 PROPERTY-VIEW-1: single-property dashboard (owner-gated).
     */
    public function show(Property $property, NoiService $noi): Response
    {
        abort_unless((int) $property->landlord_id === $this->getLandlordId(), 404);

        $buildings = $property->buildings()
            ->withCount('units')
            ->withCount(['units as occupied_units_count' => fn ($q) => $q->where('status', 'occupied')])
            ->orderBy('name')
            ->get(['id', 'name', 'building_type'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'building_type' => $b->building_type,
                'unit_count' => $b->units_count,
                'occupied_count' => $b->occupied_units_count,
                'occupancy_pct' => $b->units_count > 0 ? round($b->occupied_units_count / $b->units_count * 100, 1) : 0.0,
            ]);

        $noiRow = collect($noi->byProperty($this->getLandlordId())['properties'])
            ->firstWhere('property_id', $property->id);

        return Inertia::render('Properties/Show', [
            'property' => ['id' => $property->id, 'name' => $property->name, 'type' => $property->type, 'address' => $property->address, 'estimated_value' => $property->estimated_value],
            'metrics' => $this->metrics->forProperty($property),
            'buildings' => $buildings,
            'noi' => $noiRow,
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
