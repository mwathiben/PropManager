<?php

namespace App\Http\Controllers;

use App\Enums\MoveOutStatus;
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
use App\Models\User;
use App\Services\MoveOut\MoveOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
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

        $stats = [
            'active' => MoveOut::where('landlord_id', $landlordId)->active()->count(),
            'inspection_pending' => MoveOut::where('landlord_id', $landlordId)->status(MoveOutStatus::InspectionPending)->count(),
            'settlement_pending' => MoveOut::where('landlord_id', $landlordId)->status(MoveOutStatus::SettlementPending)->count(),
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
    public function store(StoreMoveOutRequest $request, Lease $lease, MoveOutService $service)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($lease->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($lease->moveOut()->active()->exists()) {
            return Redirect::back()->withErrors(['lease' => 'Move-out already in progress.']);
        }

        try {
            $moveOut = $service->initiate($landlordId, $lease, $request->validated(), $user);

            return Redirect::route('move-outs.show', $moveOut)->with('success', 'Move-out process initiated.');
        } catch (\Throwable $e) {
            Log::error('Failed to initiate move-out', [
                'lease_id' => $lease->id,
                'error' => $e->getMessage(),
            ]);

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
    public function startInspection(StartMoveOutInspectionRequest $request, MoveOut $moveOut, MoveOutService $service)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        try {
            $service->startInspection($moveOut, $landlordId, $request->validated(), $user);

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
    public function addDeduction(StoreMoveOutDeductionRequest $request, MoveOut $moveOut, MoveOutService $service)
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

        $service->addDeduction($moveOut, $request->validated(), $request->file('photo'));

        return Redirect::back()->with('success', 'Deduction added.');
    }

    /**
     * Update a deduction
     */
    public function updateDeduction(UpdateMoveOutDeductionRequest $request, MoveOutDeduction $deduction, MoveOutService $service)
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

        $service->updateDeduction($deduction, $moveOut, $request->validated());

        return Redirect::back()->with('success', 'Deduction updated.');
    }

    /**
     * Delete a deduction
     */
    public function deleteDeduction(MoveOutDeduction $deduction, MoveOutService $service)
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

        $service->deleteDeduction($deduction, $moveOut);

        return Redirect::back()->with('success', 'Deduction removed.');
    }

    /**
     * Complete inspection and move to settlement
     */
    public function completeInspection(CompleteMoveOutInspectionRequest $request, MoveOut $moveOut, MoveOutService $service)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        $service->completeInspection($moveOut, $landlordId, $request->validated(), $user);

        return Redirect::back()->with('success', 'Inspection completed. Ready for settlement.');
    }

    /**
     * Complete the move-out process (settle deposit)
     */
    public function complete(CompleteMoveOutSettlementRequest $request, MoveOut $moveOut, MoveOutService $service)
    {
        /** @var User $user */
        $user = Auth::user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        if ($moveOut->landlord_id !== $landlordId) {
            abort(403);
        }

        if ($moveOut->status !== MoveOutStatus::SettlementPending) {
            return Redirect::back()->withErrors(['move_out' => 'Inspection must be completed first.']);
        }

        try {
            $service->complete($moveOut, $landlordId, $request->validated(), $user);

            return Redirect::route('move-outs.show', $moveOut)->with('success', 'Move-out completed successfully.');
        } catch (\Throwable $e) {
            Log::error('move-out complete failed', [
                'move_out_id' => $moveOut->id,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()->withErrors(['move_out' => 'Failed to complete move-out.']);
        }
    }

    /**
     * Cancel a move-out process
     */
    public function cancel(CancelMoveOutRequest $request, MoveOut $moveOut, MoveOutService $service)
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

        $service->cancel($moveOut, $landlordId, $request->validated(), $user);

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

        // Phase-59 SIGNED-URLS-3: documented exception. Move-out
        // deduction photos were originally written through the 'private'
        // alias-disk (Phase-1 UPLOAD-5), which points at
        // storage/app/private/ — a different directory than the tenant
        // disk (storage/app/). Flipping the read to Storage::tenant()
        // without a path-migration would 404 every existing photo.
        // Keep on 'private' until a deliberate migration cycle moves
        // the underlying files. See docs/runbooks/storage.md.
        return Storage::disk('private')->response($deduction->photo_path);
    }
}
