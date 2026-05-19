<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWaterReadingRequest;
use App\Http\Requests\WaterReading\ApproveWaterReadingRequest;
use App\Http\Requests\WaterReading\RejectWaterReadingRequest;
use App\Http\Requests\WaterReading\UpdateWaterReadingRequest;
use App\Models\WaterReading;
use App\Services\WaterReadingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WaterReadingController extends Controller
{
    public function __construct(
        protected WaterReadingService $waterReadingService
    ) {}

    public function index(Request $request)
    {
        $property = $this->waterReadingService->getPropertyForUser(auth()->user());

        if (! $property) {
            return redirect()->route('dashboard')->with('error', 'No property found.');
        }

        $buildingsData = $this->waterReadingService->getBuildingsWithUnits($property);

        return Inertia::render('Readings/Index', [
            'buildings' => $buildingsData,
        ]);
    }

    public function store(StoreWaterReadingRequest $request)
    {
        $landlordId = $this->waterReadingService->getLandlordId(auth()->user());
        $result = $this->waterReadingService->storeReadings($request->validated()['readings'], $landlordId);

        if ($result['successCount'] > 0 && count($result['errors']) === 0) {
            return redirect()->back()->with('success', "{$result['successCount']} water reading(s) submitted for landlord approval.");
        } elseif ($result['successCount'] > 0) {
            return redirect()->back()->with('warning', "{$result['successCount']} reading(s) submitted, but some failed: ".json_encode($result['errors']));
        }

        return redirect()->back()->with('error', 'Failed to submit readings: '.json_encode($result['errors']));
    }

    public function history(Request $request)
    {
        $property = $this->waterReadingService->getPropertyForUser(auth()->user());

        if (! $property) {
            return redirect()->route('dashboard')->with('error', 'No property found.');
        }

        $buildings = $property->buildings()->with('units')->get();
        $unitIds = $buildings->flatMap(fn ($b) => $b->units->pluck('id'));

        $readings = $this->waterReadingService->getFilteredHistory(
            $unitIds,
            $request->only(['building_id', 'unit_id', 'date_from', 'date_to', 'invoiced'])
        );

        return Inertia::render('Readings/History', [
            'readings' => $readings,
            'buildings' => $buildings,
            'filters' => $request->only(['building_id', 'unit_id', 'date_from', 'date_to', 'invoiced']),
        ]);
    }

    public function update(UpdateWaterReadingRequest $request, WaterReading $reading)
    {
        // PRIV-2: WaterReadingPolicy::update exists but was never invoked.
        // Without this gate a tenant under landlord X could PUT against any
        // reading bound by TenantScope (anything under the same landlord_id).
        $this->authorize('update', $reading);

        $validated = $request->validated();

        // Phase-64 OFFLINE-MOUNTS-3: optimistic-concurrency check.
        // Non-PWA clients omit If-Match and skip the assertion
        // (backward-compatible) per the Phase 62 contract.
        $ifMatch = $request->header('If-Match');
        $reading->assertIfMatch(
            $ifMatch === null ? null : (int) $ifMatch,
            $validated,
        );

        $error = $this->waterReadingService->validateReadingUpdate($reading, $validated['current_reading']);
        if ($error) {
            return redirect()->back()->with('error', $error);
        }

        $reading->update($validated);

        return redirect()->back()->with('success', 'Water reading updated successfully.');
    }

    public function destroy(WaterReading $reading)
    {
        // PRIV-2: same policy gap on delete — symmetric authorize() call.
        $this->authorize('delete', $reading);

        if (! $this->waterReadingService->canDeleteReading($reading)) {
            return redirect()->back()->with('error', 'Cannot delete reading that has been invoiced.');
        }

        $reading->delete();

        return redirect()->back()->with('success', 'Water reading deleted successfully.');
    }

    public function review(Request $request)
    {
        $property = $this->waterReadingService->getPropertyForUser(auth()->user());

        if (! $property) {
            return redirect()->route('dashboard')->with('error', 'No property found.');
        }

        $buildings = $property->buildings()->with('units')->get();
        $unitIds = $buildings->flatMap(fn ($b) => $b->units->pluck('id'));

        $pendingReadings = $this->waterReadingService->getPendingReadings(
            $unitIds,
            $request->only(['building_id', 'date_from', 'date_to'])
        );

        return Inertia::render('Readings/Review', [
            'pendingReadings' => $pendingReadings,
            'buildings' => $buildings,
            'filters' => $request->only(['building_id', 'date_from', 'date_to']),
        ]);
    }

    public function approve(ApproveWaterReadingRequest $request, WaterReading $reading)
    {
        if ($reading->status === \App\Enums\WaterReadingStatus::Approved) {
            return redirect()->back()->with('error', 'Reading is already approved.');
        }

        if ($reading->is_invoiced) {
            return redirect()->back()->with('error', 'Cannot approve reading that has been invoiced.');
        }

        $validated = $request->validated();
        $reading->approve(auth()->id(), $validated['notes'] ?? null);

        return redirect()->back()->with('success', 'Water reading approved successfully.');
    }

    public function reject(RejectWaterReadingRequest $request, WaterReading $reading)
    {
        if ($reading->status === \App\Enums\WaterReadingStatus::Rejected) {
            return redirect()->back()->with('error', 'Reading is already rejected.');
        }

        if ($reading->is_invoiced) {
            return redirect()->back()->with('error', 'Cannot reject reading that has been invoiced.');
        }

        $validated = $request->validated();
        $reading->reject(auth()->id(), $validated['reason']);

        return redirect()->back()->with('success', 'Water reading rejected successfully.');
    }

    public function photo(WaterReading $reading)
    {
        $user = auth()->user();
        $landlordId = $this->waterReadingService->getLandlordId($user);

        if (! in_array($user->role, ['landlord', 'caretaker'])) {
            abort(403, 'Unauthorized access to water reading photo.');
        }

        if ($reading->landlord_id !== $landlordId) {
            abort(403, 'Unauthorized access to water reading photo.');
        }

        if (! $reading->hasPhoto()) {
            abort(404, 'Photo not found.');
        }

        // Phase-59 ACCESS-AUDIT-2: PII audit trail. Fail-soft.
        app(\App\Services\Storage\FileAccessRecorder::class)->record(
            $user,
            $reading,
            \App\Models\FileAccessAudit::ACTION_VIEW,
            request(),
            $reading->photo_path,
        );

        // Phase-59 SIGNED-URLS-2: 302 to short-lived signed URL with
        // inline disposition so the browser previews the image.
        return redirect()->away(
            app(\App\Services\Storage\TenantDiskResolver::class)->temporaryUrl(
                $reading->photo_path,
                $reading->landlord_id,
                5,
                null,
                'inline',
            ),
        );
    }
}
