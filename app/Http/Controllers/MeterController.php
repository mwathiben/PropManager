<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Meter\ReplaceMeterRequest;
use App\Http\Requests\Meter\StoreMeterRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Meter;
use App\Services\Water\MeterReplacementService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-86 METER-LIFECYCLE: landlord-only management of physical water meters —
 * register a meter (with its non-zero install baseline), replace one preserving
 * consumption continuity, or decommission it. Caretakers record readings against
 * meters but never manage the fleet (see MeterPolicy).
 */
class MeterController extends Controller
{
    use WithLandlordScope;

    public function __construct(private MeterReplacementService $replacements) {}

    public function index(): Response
    {
        $landlordId = $this->getLandlordId();

        $meters = Meter::query()
            ->where('landlord_id', $landlordId)
            ->with(['unit:id,unit_number', 'building:id,name'])
            ->withCount('readings')
            ->orderByDesc('status')
            ->orderBy('id')
            ->get()
            ->map(fn (Meter $m) => [
                'id' => $m->id,
                'serial_number' => $m->serial_number,
                'status' => $m->status->value,
                'utility_type' => $m->utility_type,
                'meter_type' => $m->meter_type,
                'initial_reading' => $m->initial_reading,
                'current_value' => $m->baselineForNextReading(),
                'unit' => $m->unit?->unit_number,
                'building' => $m->building?->name,
                'installed_at' => $m->installed_at?->toDateString(),
                'decommissioned_at' => $m->decommissioned_at?->toDateString(),
                'replaced_by_meter_id' => $m->replaced_by_meter_id,
                'readings_count' => $m->readings_count,
            ]);

        $buildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->with(['units:id,building_id,unit_number'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Water/Meters/Index', [
            'meters' => $meters,
            'buildings' => $buildings,
        ]);
    }

    public function store(StoreMeterRequest $request)
    {
        Meter::create([
            'landlord_id' => $this->getLandlordId(),
            'building_id' => $request->input('building_id'),
            'unit_id' => $request->input('unit_id'),
            'parent_meter_id' => $request->input('parent_meter_id'),
            'serial_number' => $request->input('serial_number'),
            'meter_type' => $request->input('meter_type'),
            'utility_type' => 'water',
            'status' => 'active',
            'initial_reading' => $request->input('initial_reading'),
            'installed_at' => $request->input('installed_at') ?? now()->toDateString(),
            'notes' => $request->input('notes'),
        ]);

        return back()->with('success', __('meter.flash.created'));
    }

    public function replace(ReplaceMeterRequest $request, Meter $meter)
    {
        $this->authorize('replace', $meter);

        $this->replacements->replace(
            $meter,
            (float) $request->input('old_final_reading'),
            (string) ($request->input('new_serial') ?? ''),
            (float) $request->input('new_initial_reading'),
            $request->input('reading_date'),
        );

        return back()->with('success', __('meter.flash.replaced'));
    }

    public function decommission(Meter $meter)
    {
        $this->authorize('decommission', $meter);

        $this->replacements->decommission($meter);

        return back()->with('success', __('meter.flash.decommissioned'));
    }
}
