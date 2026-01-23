<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkOperations\AdjustDepositsRequest;
use App\Http\Requests\BulkOperations\AdjustRentRequest;
use App\Http\Requests\BulkOperations\ExtendLeasesRequest;
use App\Http\Requests\BulkOperations\TerminateLeasesRequest;
use App\Http\Requests\BulkOperations\UpdateMeterNumbersRequest;
use App\Http\Requests\BulkOperations\UpdateTargetRentRequest;
use App\Http\Requests\BulkOperations\UpdateUnitStatusRequest;
use App\Jobs\SendNotificationJob;
use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\BulkOperations\BulkRentAdjuster;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BulkOperationsController extends Controller
{
    use HasBuildingFilter;

    public function index(Request $request): Response
    {
        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        // Get properties and buildings for filters (including wings)
        $properties = Property::where('landlord_id', $landlordId)
            ->with(['buildings' => function ($query) {
                $query->whereNull('parent_building_id')
                    ->with('wings:id,name,parent_building_id')
                    ->select('id', 'name', 'property_id', 'is_wing', 'parent_building_id');
            }])
            ->get(['id', 'name']);

        // Get main buildings for building filter
        $buildings = $this->getBuildingsForFilter();

        // Get all units with their current status and lease info
        $units = Unit::whereHas('building.property', function ($query) use ($landlordId) {
            $query->where('landlord_id', $landlordId);
        })
            ->with([
                'building:id,name,property_id,parent_building_id,is_wing',
                'building.property:id,name',
                'activeLease:id,unit_id,tenant_id,rent_amount,is_active',
                'activeLease.tenant:id,name,email,mobile_number',
            ])
            ->get();

        // Get all tenants for lease operations
        $tenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile_number']);

        return Inertia::render('BulkOperations/Index', [
            'properties' => $properties,
            'buildings' => $buildings,
            'units' => $units,
            'tenants' => $tenants,
        ]);
    }

    protected function validateLeasesBelongToBuilding(array $leaseIds, ?int $buildingId, ?int $wingId): bool
    {
        if (! $buildingId && ! $wingId) {
            return true; // No building filter, skip validation
        }

        $buildingIds = $this->getBuildingIds($buildingId, $wingId);

        $count = Lease::whereIn('id', $leaseIds)
            ->whereHas('unit', function ($query) use ($buildingIds) {
                $query->whereIn('building_id', $buildingIds);
            })
            ->count();

        return $count === count($leaseIds);
    }

    protected function validateUnitsBelongToBuilding(array $unitIds, ?int $buildingId, ?int $wingId): bool
    {
        if (! $buildingId && ! $wingId) {
            return true; // No building filter, skip validation
        }

        $buildingIds = $this->getBuildingIds($buildingId, $wingId);

        $count = Unit::whereIn('id', $unitIds)
            ->whereIn('building_id', $buildingIds)
            ->count();

        return $count === count($unitIds);
    }

    public function adjustRent(AdjustRentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (! $this->validateLeasesBelongToBuilding(
            $validated['lease_ids'],
            $validated['building_id'] ?? null,
            $validated['wing_id'] ?? null
        )) {
            return redirect()->back()->with('error',
                'Some selected leases do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        try {
            $results = BulkRentAdjuster::forLeases($validated['lease_ids'], $landlordId)
                ->withAdjustmentType($validated['adjustment_type'])
                ->withValue($validated['adjustment_value'])
                ->withReason($validated['reason'] ?? null)
                ->withEffectiveDate($validated['effective_date'])
                ->shouldNotifyTenants($validated['notify_tenants'] ?? false)
                ->execute();

            return redirect()->back()->with('success', sprintf(
                'Rent adjusted for %d of %d leases.',
                $results['success'],
                $results['total']
            ))->with('bulk_results', $results);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Bulk rent adjustment failed: '.$e->getMessage());
        }
    }

    public function updateUnitStatus(UpdateUnitStatusRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all units belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;

        if (! $this->validateUnitsBelongToBuilding($validated['unit_ids'], $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected units do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['unit_ids']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['unit_ids'] as $unitId) {
                try {
                    $unit = Unit::whereHas('building.property', function ($query) use ($landlordId) {
                        $query->where('landlord_id', $landlordId);
                    })
                        ->where('id', $unitId)
                        ->firstOrFail();

                    $oldStatus = $unit->status;
                    $unit->update(['status' => $validated['new_status']]);

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'unit_id' => $unitId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Status updated for %d of %d units to "%s".',
                $results['success'],
                $results['total'],
                $validated['new_status']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk unit status update failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk status update failed: '.$e->getMessage());
        }
    }

    public function terminateLeases(TerminateLeasesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all leases belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;

        if (! $this->validateLeasesBelongToBuilding($validated['lease_ids'], $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected leases do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['lease_ids']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['lease_ids'] as $leaseId) {
                try {
                    $lease = Lease::where('id', $leaseId)
                        ->where('landlord_id', $landlordId)
                        ->where('is_active', true)
                        ->with(['tenant:id,name', 'unit:id,status'])
                        ->firstOrFail();

                    // Terminate lease
                    $lease->update([
                        'is_active' => false,
                        'end_date' => $validated['termination_date'],
                    ]);

                    // Update unit status if requested
                    if ($validated['update_unit_status'] ?? true) {
                        $lease->unit->update(['status' => 'vacant']);
                    }

                    // Notify tenant if requested
                    if ($validated['notify_tenants'] ?? false) {
                        dispatch(SendNotificationJob::forNew(
                            $lease->tenant_id,
                            'general',
                            'Lease Termination Notice',
                            sprintf(
                                "Hello %s,\n\nThis is to inform you that your lease has been terminated effective %s.\n\nReason: %s\n\nPlease contact us if you have any questions.",
                                $lease->tenant->name,
                                $validated['termination_date'],
                                $validated['reason'] ?? 'Not specified'
                            ),
                            [
                                'termination_date' => $validated['termination_date'],
                                'reason' => $validated['reason'],
                            ],
                            $landlordId
                        ))->afterCommit();
                    }

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'lease_id' => $leaseId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Terminated %d of %d leases.',
                $results['success'],
                $results['total']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk lease termination failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk lease termination failed: '.$e->getMessage());
        }
    }

    public function extendLeases(ExtendLeasesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all leases belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;

        if (! $this->validateLeasesBelongToBuilding($validated['lease_ids'], $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected leases do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['lease_ids']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['lease_ids'] as $leaseId) {
                try {
                    $lease = Lease::where('id', $leaseId)
                        ->where('landlord_id', $landlordId)
                        ->where('is_active', true)
                        ->with('tenant:id,name')
                        ->firstOrFail();

                    $oldEndDate = $lease->end_date;
                    $newEndDate = $lease->end_date
                        ? \Carbon\Carbon::parse($lease->end_date)->addMonths($validated['extension_months'])
                        : now()->addMonths($validated['extension_months']);

                    $lease->update(['end_date' => $newEndDate]);

                    // Notify tenant if requested
                    if ($validated['notify_tenants'] ?? false) {
                        dispatch(SendNotificationJob::forNew(
                            $lease->tenant_id,
                            'lease_renewal',
                            'Lease Extension Notice',
                            sprintf(
                                "Hello %s,\n\nYour lease has been extended by %d month(s). Your new lease end date is %s.\n\nThank you for being a valued tenant.",
                                $lease->tenant->name,
                                $validated['extension_months'],
                                $newEndDate->format('Y-m-d')
                            ),
                            [
                                'old_end_date' => $oldEndDate,
                                'new_end_date' => $newEndDate->format('Y-m-d'),
                                'extension_months' => $validated['extension_months'],
                            ],
                            $landlordId
                        ))->afterCommit();
                    }

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'lease_id' => $leaseId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Extended %d of %d leases by %d month(s).',
                $results['success'],
                $results['total'],
                $validated['extension_months']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk lease extension failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk lease extension failed: '.$e->getMessage());
        }
    }

    public function adjustDeposits(AdjustDepositsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all leases belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;

        if (! $this->validateLeasesBelongToBuilding($validated['lease_ids'], $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected leases do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['lease_ids']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['lease_ids'] as $leaseId) {
                try {
                    $lease = Lease::where('id', $leaseId)
                        ->where('landlord_id', $landlordId)
                        ->where('is_active', true)
                        ->with('tenant:id,name')
                        ->firstOrFail();

                    $oldDeposit = $lease->deposit_amount ?? 0;

                    // Calculate new deposit
                    if ($validated['adjustment_type'] === 'percentage') {
                        $newDeposit = $oldDeposit * (1 + ($validated['adjustment_value'] / 100));
                    } elseif ($validated['adjustment_type'] === 'fixed') {
                        $newDeposit = $oldDeposit + $validated['adjustment_value'];
                    } else { // set
                        $newDeposit = $validated['adjustment_value'];
                    }

                    $newDeposit = max(0, round($newDeposit, 2));

                    $lease->update(['deposit_amount' => $newDeposit]);

                    // Notify tenant if requested
                    if (($validated['notify_tenants'] ?? false) && $lease->tenant) {
                        dispatch(SendNotificationJob::forNew(
                            $lease->tenant_id,
                            'general',
                            'Deposit Adjustment Notice',
                            sprintf(
                                "Hello %s,\n\nYour deposit amount has been adjusted from KES %s to KES %s.\n\nPlease contact us if you have any questions.",
                                $lease->tenant->name,
                                number_format($oldDeposit, 2),
                                number_format($newDeposit, 2)
                            ),
                            [
                                'old_deposit' => $oldDeposit,
                                'new_deposit' => $newDeposit,
                            ],
                            $landlordId
                        ))->afterCommit();
                    }

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'lease_id' => $leaseId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Deposit adjusted for %d of %d leases.',
                $results['success'],
                $results['total']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk deposit adjustment failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk deposit adjustment failed: '.$e->getMessage());
        }
    }

    public function updateTargetRent(UpdateTargetRentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all units belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;

        if (! $this->validateUnitsBelongToBuilding($validated['unit_ids'], $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected units do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['unit_ids']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['unit_ids'] as $unitId) {
                try {
                    $unit = Unit::whereHas('building.property', function ($query) use ($landlordId) {
                        $query->where('landlord_id', $landlordId);
                    })
                        ->where('id', $unitId)
                        ->firstOrFail();

                    $oldTargetRent = $unit->target_rent ?? 0;

                    // Calculate new target rent
                    if ($validated['adjustment_type'] === 'percentage') {
                        $newTargetRent = $oldTargetRent * (1 + ($validated['adjustment_value'] / 100));
                    } elseif ($validated['adjustment_type'] === 'fixed') {
                        $newTargetRent = $oldTargetRent + $validated['adjustment_value'];
                    } else { // set
                        $newTargetRent = $validated['adjustment_value'];
                    }

                    $newTargetRent = max(0, round($newTargetRent, 2));

                    $unit->update(['target_rent' => $newTargetRent]);

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'unit_id' => $unitId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Target rent updated for %d of %d units.',
                $results['success'],
                $results['total']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk target rent update failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk target rent update failed: '.$e->getMessage());
        }
    }

    public function updateMeterNumbers(UpdateMeterNumbersRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Strict enforcement: validate all units belong to selected building/wing
        $buildingId = $validated['building_id'] ?? null;
        $wingId = $validated['wing_id'] ?? null;
        $unitIds = array_column($validated['updates'], 'unit_id');

        if (! $this->validateUnitsBelongToBuilding($unitIds, $buildingId, $wingId)) {
            return redirect()->back()->with('error',
                'Some selected units do not belong to the selected building/wing. Please verify your selection.');
        }

        $user = auth()->user();
        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;

        $results = [
            'total' => count($validated['updates']),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($validated['updates'] as $update) {
                try {
                    $unit = Unit::whereHas('building.property', function ($query) use ($landlordId) {
                        $query->where('landlord_id', $landlordId);
                    })
                        ->where('id', $update['unit_id'])
                        ->firstOrFail();

                    $unit->update(['meter_number' => $update['meter_number']]);

                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'unit_id' => $update['unit_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return redirect()->back()->with('success', sprintf(
                'Meter numbers updated for %d of %d units.',
                $results['success'],
                $results['total']
            ));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk meter number update failed', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Bulk meter number update failed: '.$e->getMessage());
        }
    }
}
