<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Unit;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        // Phase-79 WATER-GATE-3: the conditional-module gate now lives in the
        // 'water.module' route middleware (plan AND charges-for-water), so the
        // old plan-only check here is gone.
        $landlordId = $this->getLandlordId();

        // Phase-79 WATER-ROLES-1: the hub is role-aware. The caretaker RECORDS
        // readings (input); the landlord only REVIEWS (approve/reject). Neither
        // sees the other's primary tab.
        $isCaretaker = auth()->user()->isCaretaker();
        $defaultTab = $isCaretaker ? 'readings' : 'review';
        $tab = $request->query('tab', $defaultTab);
        if ($tab === 'readings' && ! $isCaretaker) {
            $tab = 'review';
        }
        if ($tab === 'review' && $isCaretaker) {
            $tab = 'readings';
        }

        $baseProps = [
            'activeTab' => $tab,
            'role' => $isCaretaker ? 'caretaker' : 'landlord',
            'canInput' => $isCaretaker,
            'canReview' => ! $isCaretaker,
            'filters' => $request->only(['building_id', 'unit_id', 'date_from', 'date_to', 'status']),
            'buildings' => $this->getBuildings($landlordId),
            'counts' => $this->getCounts($landlordId),
        ];

        $tabData = match ($tab) {
            'readings' => $this->getReadingsData($landlordId),
            'review' => $this->getReviewData($landlordId),
            'history' => $this->getHistoryData($request, $landlordId),
            'settings' => $this->getSettingsData($landlordId),
            default => $isCaretaker ? $this->getReadingsData($landlordId) : $this->getReviewData($landlordId),
        };

        return Inertia::render('Water/Hub', array_merge($baseProps, $tabData));
    }

    private function getReadingsData(int $landlordId): array
    {
        // Phase-79: landlord-wide (all properties' buildings, not just the
        // first) and no has_water_meter filter — that column never existed, so
        // the old query 500'd the readings tab. Every unit can hold a reading.
        $buildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->with(['units' => function ($q) {
                $q->orderBy('unit_number')
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

    /**
     * Phase-79 WATER-ROLES-3: the landlord's review surface — pending readings
     * awaiting approve/reject, landlord-wide (all buildings, not just the first
     * property).
     */
    private function getReviewData(int $landlordId): array
    {
        $pending = WaterReading::query()
            ->whereIn('unit_id', $this->unitIdsForLandlord($landlordId))
            ->where('status', 'pending')
            ->with(['unit.building', 'recorder:id,name'])
            ->orderBy('reading_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return ['pendingReadings' => $pending];
    }

    /**
     * @return list<int>
     */
    private function unitIdsForLandlord(int $landlordId): array
    {
        return Unit::query()->where('landlord_id', $landlordId)->pluck('id')->all();
    }

    private function getHistoryData(Request $request, int $landlordId): array
    {
        // Phase-79: landlord-wide history (all properties, not just the first).
        $buildings = Building::query()->where('landlord_id', $landlordId)->with('units')->get();
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
                $query->where('status', 'pending');
            } elseif ($request->status === 'approved') {
                $query->where('status', 'approved');
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
        // Phase-83 follow-up WATER-SETTINGS-UNIFY: the Settings tab now renders
        // the SAME canonical editor as /water/settings (global PaymentConfiguration
        // + per-building overrides — what WaterRateService actually bills from).
        // The old WaterSetting model (rate_per_unit/billing_day/is_enabled) was an
        // orphan no billing path read, so it is retired here.
        return [
            'settings' => \App\Services\Water\WaterSettingsData::forLandlord($landlordId),
        ];
    }

    private function getCounts(int $landlordId): array
    {
        return [
            'pendingReadings' => WaterReading::whereIn('unit_id', $this->unitIdsForLandlord($landlordId))
                ->where('status', 'pending')
                ->count(),
        ];
    }
}
