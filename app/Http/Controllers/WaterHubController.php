<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Property;
use App\Models\WaterReading;
use App\Models\WaterSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $user = auth()->user();
        $landlord = $user->isCaretaker() ? $user->landlord : $user;

        if (! $landlord?->canAccessFeature('water_billing')) {
            return redirect()->route('dashboard')
                ->with('error', 'Water billing feature is not enabled for your subscription.');
        }

        $tab = $request->query('tab', 'readings');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['building_id', 'unit_id', 'date_from', 'date_to', 'status']),
            'buildings' => $this->getBuildings($landlordId),
            'counts' => $this->getCounts($landlordId),
        ];

        $tabData = match ($tab) {
            'readings' => $this->getReadingsData($landlordId),
            'history' => $this->getHistoryData($request, $landlordId),
            'settings' => $this->getSettingsData($landlordId),
            default => $this->getReadingsData($landlordId),
        };

        return Inertia::render('Water/Hub', array_merge($baseProps, $tabData));
    }

    private function getReadingsData(int $landlordId): array
    {
        $property = Property::where('landlord_id', $landlordId)->first();

        if (! $property) {
            return ['buildings' => []];
        }

        $buildings = $property->buildings()
            ->with(['units' => function ($q) {
                $q->where('has_water_meter', true)
                    ->orderBy('unit_number')
                    ->with(['waterReadings' => function ($q) {
                        $q->latest('reading_date')->limit(1);
                    }]);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($building) {
                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'units' => $building->units->map(function ($unit) {
                        $lastReading = $unit->waterReadings->first();

                        return [
                            'id' => $unit->id,
                            'unit_number' => $unit->unit_number,
                            'last_reading' => $lastReading ? [
                                'current_reading' => $lastReading->current_reading,
                                'reading_date' => $lastReading->reading_date,
                            ] : null,
                        ];
                    }),
                ];
            });

        return ['buildingsData' => $buildings];
    }

    private function getHistoryData(Request $request, int $landlordId): array
    {
        $property = Property::where('landlord_id', $landlordId)->first();

        if (! $property) {
            return ['readings' => [], 'buildings' => []];
        }

        $buildings = $property->buildings()->with('units')->get();
        $unitIds = $buildings->flatMap(fn ($b) => $b->units->pluck('id'));

        $query = WaterReading::whereIn('unit_id', $unitIds)
            ->with(['unit.building']);

        if ($request->filled('building_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('reading_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('reading_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'invoiced') {
                $query->where('is_invoiced', true);
            }
        }

        $readings = $query->orderBy('reading_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return [
            'readings' => $readings,
            'buildingsList' => $buildings,
        ];
    }

    private function getSettingsData(int $landlordId): array
    {
        $settings = WaterSetting::where('landlord_id', $landlordId)->first();

        if (! $settings) {
            $settings = WaterSetting::create([
                'landlord_id' => $landlordId,
                'rate_per_unit' => 150,
                'billing_day' => 1,
                'is_enabled' => true,
            ]);
        }

        return [
            'settings' => $settings,
        ];
    }

    private function getCounts(int $landlordId): array
    {
        $property = Property::where('landlord_id', $landlordId)->first();
        $unitIds = [];

        if ($property) {
            $unitIds = $property->buildings()
                ->with('units')
                ->get()
                ->flatMap(fn ($b) => $b->units->pluck('id'))
                ->toArray();
        }

        return [
            'pendingReadings' => WaterReading::whereIn('unit_id', $unitIds)
                ->where('is_approved', false)
                ->count(),
        ];
    }
}
