<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Meter\ReplaceMeterRequest;
use App\Http\Requests\Meter\StoreMeterRequest;
use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Meter;
use App\Models\TenantActivity;
use App\Models\Unit;
use App\Services\Water\MeterReplacementService;
use Illuminate\Http\Request;
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
            ->with(['unit:id,unit_number', 'building:id,name', 'latestReading:id,meter_id,current_reading'])
            ->withCount(['readings', 'subMeters'])
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
                'current_value' => (float) ($m->latestReading->current_reading ?? $m->initial_reading),
                'unit' => $m->unit?->unit_number,
                'building' => $m->building?->name,
                'installed_at' => $m->installed_at?->toDateString(),
                'decommissioned_at' => $m->decommissioned_at?->toDateString(),
                'disconnected_at' => $m->disconnected_at?->toDateString(),
                // Only a unit's own meter may be disconnected (not a shared/main meter).
                'is_unit_meter' => $m->unit_id !== null && $m->parent_meter_id === null && $m->sub_meters_count === 0,
                'replaced_by_meter_id' => $m->replaced_by_meter_id,
                'readings_count' => $m->readings_count,
            ]);

        $buildings = Building::query()
            ->where('landlord_id', $landlordId)
            ->with(['units:id,building_id,unit_number'])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Phase-90: units in water arrears (overdue invoices carrying a water charge)
        // — surfaced next to the meters so the landlord can act (disconnect).
        $arrears = app(\App\Services\Water\WaterArrearsService::class)
            ->overdueWaterInvoices($landlordId)
            ->map(fn (\App\Models\Invoice $inv) => [
                'invoice_id' => $inv->id,
                'unit' => $inv->lease?->unit?->unit_number,
                'building' => $inv->lease?->unit?->building?->name,
                'tenant' => $inv->lease?->tenant?->name,
                'water_due' => (float) $inv->water_due,
                'outstanding' => (float) ($inv->total_due - $inv->amount_paid),
                'due_date' => $inv->due_date?->toDateString(),
            ]);

        return Inertia::render('Water/Meters/Index', [
            'meters' => $meters,
            'buildings' => $buildings,
            'arrears' => $arrears,
        ]);
    }

    public function store(StoreMeterRequest $request)
    {
        $unitId = $request->input('unit_id');
        // Review (CR M2): keep building_id consistent with the unit rather than
        // trusting a possibly-mismatched client value.
        $buildingId = $unitId
            ? Unit::whereKey($unitId)->value('building_id')
            : $request->input('building_id');

        Meter::create([
            'landlord_id' => $this->getLandlordId(),
            'building_id' => $buildingId,
            'unit_id' => $unitId,
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

        try {
            $this->replacements->replace(
                $meter,
                (float) $request->input('old_final_reading'),
                (string) ($request->input('new_serial') ?? ''),
                (float) $request->input('new_initial_reading'),
                $request->input('reading_date'),
            );
        } catch (\InvalidArgumentException $e) {
            // Review (CR1): user-correctable conditions (below-baseline, already
            // replaced) belong on the form, not a 500 — matches BuildingController.
            return back()->withErrors(['old_final_reading' => $e->getMessage()]);
        }

        return back()->with('success', __('meter.flash.replaced'));
    }

    public function decommission(Meter $meter)
    {
        $this->authorize('decommission', $meter);

        try {
            $this->replacements->decommission($meter);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['meter' => $e->getMessage()]);
        }

        return back()->with('success', __('meter.flash.decommissioned'));
    }

    /**
     * Phase-90: cut water service for non-payment. THE CAVEAT — only a unit's own
     * meter (never a shared/main meter feeding sub-meters).
     */
    public function disconnect(Request $request, Meter $meter)
    {
        $this->authorize('disconnect', $meter);

        if (! $meter->isUnitMeter()) {
            return back()->withErrors(['meter' => __('meter.disconnect.not_unit_meter')]);
        }
        if ($meter->isDisconnected()) {
            return back()->with('info', __('meter.disconnect.already'));
        }

        $reason = trim((string) $request->input('reason'));
        $meter->update([
            'disconnected_at' => now(),
            'disconnect_reason' => $reason !== '' ? $reason : 'Non-payment',
        ]);
        $this->auditService($meter, TenantActivity::TYPE_WATER_METER_DISCONNECTED, 'Water service disconnected');

        return back()->with('success', __('meter.flash.disconnected'));
    }

    public function reconnect(Meter $meter)
    {
        $this->authorize('reconnect', $meter);

        if (! $meter->isDisconnected()) {
            return back()->with('info', __('meter.disconnect.not_disconnected'));
        }

        $meter->update(['disconnected_at' => null, 'disconnect_reason' => null]);
        // Phase-90 RECONNECT-FEE: charge the configured fee on the next invoice.
        $fee = app(\App\Services\Water\WaterReconnectionService::class)->chargeFee($meter);
        $this->auditService($meter, TenantActivity::TYPE_WATER_METER_RECONNECTED, 'Water service reconnected');

        return back()->with('success', $fee > 0
            ? __('meter.flash.reconnected_fee', ['amount' => number_format($fee, 2)])
            : __('meter.flash.reconnected'));
    }

    private function auditService(Meter $meter, string $type, string $description): void
    {
        $tenantId = $meter->unit?->activeLease?->tenant_id;
        if (! $tenantId) {
            return;
        }

        TenantActivity::create([
            'landlord_id' => $meter->landlord_id,
            'tenant_id' => $tenantId,
            'type' => $type,
            'description' => "{$description} (meter #{$meter->id})",
            'metadata' => ['meter_id' => $meter->id, 'unit_id' => $meter->unit_id],
            'performed_by' => auth()->id(),
        ]);
    }
}
