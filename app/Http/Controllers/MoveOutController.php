<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveOut\CancelMoveOutRequest;
use App\Http\Requests\MoveOut\CompleteMoveOutInspectionRequest;
use App\Http\Requests\MoveOut\CompleteMoveOutSettlementRequest;
use App\Http\Requests\MoveOut\StartMoveOutInspectionRequest;
use App\Http\Requests\MoveOut\StoreMoveOutDeductionRequest;
use App\Http\Requests\MoveOut\StoreMoveOutRequest;
use App\Http\Requests\MoveOut\UpdateMoveOutDeductionRequest;
use App\Http\Requests\MoveOut\UpdateMoveOutRequest;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use App\Models\MoveOutDeductionCategory;
use App\Models\TenantActivity;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MoveOutController extends Controller
{
    /**
     * Display a list of move-outs (active and recent)
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $status = $request->get('status', 'active');

        $query = MoveOut::where('landlord_id', $landlordId)
            ->with(['lease.tenant', 'lease.unit.building.property', 'deductions.category', 'processor']);

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'completed') {
            $query->completed();
        }

        $moveOuts = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'active' => MoveOut::where('landlord_id', $landlordId)->active()->count(),
            'inspection_pending' => MoveOut::where('landlord_id', $landlordId)->status('inspection_pending')->count(),
            'settlement_pending' => MoveOut::where('landlord_id', $landlordId)->status('settlement_pending')->count(),
            'completed_this_month' => MoveOut::where('landlord_id', $landlordId)
                ->completed()
                ->whereMonth('settled_at', now()->month)
                ->whereYear('settled_at', now()->year)
                ->count(),
        ];

        return Inertia::render('MoveOuts/Index', [
            'moveOuts' => $moveOuts,
            'status' => $status,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the initiate move-out form for a lease
     */
    public function create(Lease $lease)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        // Check if there's already an active move-out
        if ($lease->moveOut()->active()->exists()) {
            return Redirect::route('move-outs.show', $lease->moveOut()->active()->first())
                ->with('info', 'A move-out process is already in progress for this lease.');
        }

        $lease->load(['tenant', 'unit.building.property']);

        return Inertia::render('MoveOuts/Create', [
            'lease' => $lease,
        ]);
    }

    /**
     * Initiate a new move-out process
     */
    public function store(StoreMoveOutRequest $request, Lease $lease)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        // Check for existing active move-out
        if ($lease->moveOut()->active()->exists()) {
            return Redirect::back()->withErrors(['lease' => 'Move-out already in progress.']);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $moveOut = MoveOut::create([
                'landlord_id' => $landlordId,
                'lease_id' => $lease->id,
                'notice_date' => $validated['notice_date'],
                'intended_move_out_date' => $validated['intended_move_out_date'],
                'status' => 'notice_given',
                'deposit_held' => $lease->deposit_amount,
                'arrears_balance' => $lease->arrears ?? 0,
                'total_deductions' => 0,
                'refund_amount' => $lease->deposit_amount - ($lease->arrears ?? 0),
            ]);

            // Log activity
            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $user->id,
                'type' => 'move_out_initiated',
                'description' => 'Move-out notice given. Expected move-out: '.$validated['intended_move_out_date'],
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'reason' => $validated['reason'] ?? null,
                ],
            ]);

            DB::commit();

            return Redirect::route('move-outs.show', $moveOut)->with('success', 'Move-out process initiated.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['move_out' => 'Failed to initiate move-out.']);
        }
    }

    /**
     * Show a move-out in progress
     */
    public function show(MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        $moveOut->load([
            'lease.tenant',
            'lease.unit.building.property',
            'deductions.category',
            'processor',
        ]);

        $buildingId = $moveOut->lease->unit->building_id;
        $categories = MoveOutDeductionCategory::query()
            ->where('landlord_id', $landlordId)
            ->where(function ($q) use ($buildingId) {
                $q->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            })
            ->active()
            ->ordered()
            ->get(['id', 'name', 'description', 'default_amount', 'always_apply']);

        return Inertia::render('MoveOuts/Show', [
            'moveOut' => $moveOut,
            'categories' => $categories,
        ]);
    }

    /**
     * Update move-out details (dates, notes)
     */
    public function update(UpdateMoveOutRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->isCompleted() || $moveOut->isCancelled()) {
            return Redirect::back()->withErrors(['move_out' => 'Cannot update a completed or cancelled move-out.']);
        }

        $moveOut->update($request->validated());

        return Redirect::back()->with('success', 'Move-out updated.');
    }

    /**
     * Mark actual move-out date and start inspection
     */
    public function startInspection(StartMoveOutInspectionRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($moveOut, $validated, $landlordId, $user) {
                $lockedMoveOut = MoveOut::where('id', $moveOut->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedMoveOut->status !== 'notice_given') {
                    throw new \RuntimeException('Inspection already started or move-out in wrong state.');
                }

                $lockedMoveOut->update([
                    'actual_move_out_date' => $validated['actual_move_out_date'],
                    'status' => 'inspection_pending',
                ]);

                $this->autoApplyDeductions($lockedMoveOut, $landlordId);

                TenantActivity::create([
                    'landlord_id' => $landlordId,
                    'tenant_id' => $lockedMoveOut->lease->tenant_id,
                    'performed_by' => $user->id,
                    'type' => 'move_out_inspection_started',
                    'description' => 'Tenant moved out. Inspection started.',
                    'metadata' => ['move_out_id' => $lockedMoveOut->id],
                ]);
            });

            return Redirect::back()->with('success', 'Inspection started.');
        } catch (\RuntimeException $e) {
            return Redirect::back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to start inspection', [
                'move_out_id' => $moveOut->id,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->withErrors(['move_out' => 'Failed to start inspection. Please try again.']);
        }
    }

    /**
     * Add a deduction to the move-out
     */
    public function addDeduction(StoreMoveOutDeductionRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->isCompleted() || $moveOut->isCancelled()) {
            return Redirect::back()->withErrors(['move_out' => 'Cannot add deductions to completed move-out.']);
        }

        $validated = $request->validated();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store("move-outs/{$moveOut->id}", 'private');
        }

        try {
            DB::transaction(function () use ($moveOut, $validated, $photoPath) {
                MoveOutDeduction::create([
                    'move_out_id' => $moveOut->id,
                    'category_id' => $validated['category_id'] ?? null,
                    'description' => $validated['description'],
                    'amount' => $validated['amount'],
                    'notes' => $validated['notes'] ?? null,
                    'photo_path' => $photoPath,
                    'auto_applied' => false,
                ]);

                $moveOut->calculateRefund();
                $moveOut->save();
            });

            return Redirect::back()->with('success', 'Deduction added.');
        } catch (\Exception $e) {
            if ($photoPath) {
                Storage::disk('private')->delete($photoPath);
            }
            throw $e;
        }
    }

    /**
     * Update a deduction
     */
    public function updateDeduction(UpdateMoveOutDeductionRequest $request, MoveOutDeduction $deduction)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $moveOut = $deduction->moveOut;
        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->isCompleted() || $moveOut->isCancelled()) {
            return Redirect::back()->withErrors(['move_out' => 'Cannot update deductions on completed move-out.']);
        }

        DB::transaction(function () use ($deduction, $moveOut, $request) {
            $deduction->update($request->validated());

            $moveOut->calculateRefund();
            $moveOut->save();
        });

        return Redirect::back()->with('success', 'Deduction updated.');
    }

    /**
     * Delete a deduction
     */
    public function deleteDeduction(MoveOutDeduction $deduction)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $moveOut = $deduction->moveOut;
        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->isCompleted() || $moveOut->isCancelled()) {
            return Redirect::back()->withErrors(['move_out' => 'Cannot delete deductions from completed move-out.']);
        }

        // Delete photo if exists
        if ($deduction->photo_path) {
            Storage::disk('private')->delete($deduction->photo_path);
        }

        $deduction->delete();

        // Recalculate refund
        $moveOut->calculateRefund();
        $moveOut->save();

        return Redirect::back()->with('success', 'Deduction removed.');
    }

    /**
     * Complete inspection and move to settlement
     */
    public function completeInspection(CompleteMoveOutInspectionRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($moveOut, $validated, $landlordId, $user) {
            $moveOut->update([
                'status' => 'settlement_pending',
                'inspection_notes' => $validated['inspection_notes'] ?? null,
            ]);

            $moveOut->calculateRefund();
            $moveOut->save();

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $moveOut->lease->tenant_id,
                'performed_by' => $user->id,
                'action' => 'move_out_inspection_complete',
                'description' => 'Inspection completed. Settlement pending.',
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'total_deductions' => $moveOut->total_deductions,
                    'refund_amount' => $moveOut->refund_amount,
                ],
            ]);
        });

        return Redirect::back()->with('success', 'Inspection completed. Ready for settlement.');
    }

    /**
     * Complete the move-out process (settle deposit)
     */
    public function complete(CompleteMoveOutSettlementRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->status !== 'settlement_pending') {
            return Redirect::back()->withErrors(['move_out' => 'Inspection must be completed first.']);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Final calculations
            $moveOut->calculateRefund();

            $moveOut->update([
                'status' => 'completed',
                'settlement_method' => $validated['settlement_method'],
                'settlement_reference' => $validated['settlement_reference'] ?? null,
                'settled_at' => now(),
                'processed_by' => $user->id,
            ]);

            // Deactivate the lease
            $lease = $moveOut->lease;
            $lease->update([
                'is_active' => false,
                'end_date' => $moveOut->actual_move_out_date ?? now(),
            ]);

            // Mark unit as vacant
            $lease->unit->update(['status' => 'vacant']);

            // Log activity
            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $lease->tenant_id,
                'performed_by' => $user->id,
                'action' => 'move_out_completed',
                'description' => 'Move-out completed. Deposit settled via '.$validated['settlement_method'].'. Refund: KES '.number_format($moveOut->refund_amount, 2),
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'refund_amount' => $moveOut->refund_amount,
                    'settlement_method' => $validated['settlement_method'],
                ],
            ]);

            DB::commit();

            return Redirect::route('move-outs.show', $moveOut)->with('success', 'Move-out completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()->withErrors(['move_out' => 'Failed to complete move-out.']);
        }
    }

    /**
     * Cancel a move-out process
     */
    public function cancel(CancelMoveOutRequest $request, MoveOut $moveOut)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->isCompleted()) {
            return Redirect::back()->withErrors(['move_out' => 'Cannot cancel a completed move-out.']);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($moveOut, $validated, $landlordId, $user) {
            $moveOut->update(['status' => 'cancelled']);

            TenantActivity::create([
                'landlord_id' => $landlordId,
                'tenant_id' => $moveOut->lease->tenant_id,
                'performed_by' => $user->id,
                'action' => 'move_out_cancelled',
                'description' => 'Move-out process cancelled.',
                'metadata' => [
                    'move_out_id' => $moveOut->id,
                    'reason' => $validated['cancellation_reason'] ?? null,
                ],
            ]);
        });

        return Redirect::route('tenants.show', $moveOut->lease->tenant_id)->with('success', 'Move-out cancelled.');
    }

    /**
     * Get deduction photo
     */
    public function deductionPhoto(MoveOutDeduction $deduction)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($deduction->moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if (! $deduction->photo_path || ! Storage::disk('private')->exists($deduction->photo_path)) {
            abort(404);
        }

        return Storage::disk('private')->response($deduction->photo_path);
    }

    /**
     * Auto-apply deductions from categories with always_apply flag.
     */
    private function autoApplyDeductions(MoveOut $moveOut, int $landlordId): void
    {
        try {
            $buildingId = $moveOut->lease->unit->building_id;

            $categories = MoveOutDeductionCategory::query()
                ->where('landlord_id', $landlordId)
                ->active()
                ->alwaysApply()
                ->where(function ($query) use ($buildingId) {
                    $query->where('building_id', $buildingId)
                        ->orWhereNull('building_id');
                })
                ->ordered()
                ->get();

            $existingCategoryIds = MoveOutDeduction::where('move_out_id', $moveOut->id)
                ->where('auto_applied', true)
                ->pluck('category_id')
                ->toArray();

            $categoriesToApply = $categories->filter(function ($category) use ($existingCategoryIds) {
                return ! in_array($category->id, $existingCategoryIds);
            });

            $totalApplied = 0;
            foreach ($categoriesToApply as $category) {
                MoveOutDeduction::create([
                    'move_out_id' => $moveOut->id,
                    'category_id' => $category->id,
                    'description' => $category->name,
                    'amount' => $category->default_amount,
                    'auto_applied' => true,
                ]);
                $totalApplied++;
            }

            if ($totalApplied > 0) {
                $moveOut->calculateRefund();
                $moveOut->save();

                Log::info('Auto-applied move-out deductions', [
                    'move_out_id' => $moveOut->id,
                    'building_id' => $buildingId,
                    'count' => $totalApplied,
                    'category_ids' => $categoriesToApply->pluck('id')->toArray(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-apply deductions', [
                'move_out_id' => $moveOut->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
