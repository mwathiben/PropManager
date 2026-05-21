<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreLateFeeRuleRequest;
use App\Http\Requests\Finance\UpdateLateFeeRuleRequest;
use App\Http\Requests\Finance\WaiveLateFeeRequest;
use App\Http\Traits\WithETag;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Models\Invoice;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Services\FinanceStatsService;
use App\Services\LateFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class LateFeeController extends Controller
{
    use WithETag;
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceStatsService $statsService,
    ) {}

    public function index(): Response
    {
        $landlordId = $this->getLandlordId();

        return $this->renderFinances('late-fees', [
            'policies' => $this->getLateFeePolices($landlordId),
            'properties' => $this->getProperties($landlordId),
            'buildings' => $this->getBuildings($landlordId),
            'stats' => $this->statsService->getLateFeeStats($landlordId),
        ]);
    }

    public function store(StoreLateFeeRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['priority'] = match (true) {
            isset($validated['building_id']) && $validated['building_id'] => 30,
            isset($validated['property_id']) && $validated['property_id'] => 20,
            default => 10,
        };

        $this->authorize('create', LateFeePolicy::class);

        LateFeePolicy::create($validated);

        return back()->with('success', 'Late fee policy created successfully.');
    }

    public function update(UpdateLateFeeRuleRequest $request, LateFeePolicy $policy): RedirectResponse
    {
        $this->authorize('update', $policy);

        $validated = $request->validated();

        $validated['priority'] = match (true) {
            isset($validated['building_id']) && $validated['building_id'] => 30,
            isset($validated['property_id']) && $validated['property_id'] => 20,
            default => 10,
        };

        $policy->update($validated);

        return back()->with('success', 'Late fee policy updated successfully.');
    }

    public function destroy(LateFeePolicy $policy): RedirectResponse
    {
        $this->authorize('delete', $policy);

        if ($policy->lateFees()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete policy with existing late fees. Deactivate it instead.']);
        }

        $policy->delete();

        return back()->with('success', 'Late fee policy deleted successfully.');
    }

    public function toggle(LateFeePolicy $policy): RedirectResponse
    {
        $this->authorize('update', $policy);

        $policy->update(['is_active' => ! $policy->is_active]);

        $status = $policy->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Late fee policy {$status} successfully.");
    }

    /**
     * Phase-81 LATE-FEE-DEPTH-1: apply late fees to this landlord's eligible
     * overdue invoices on demand (same eligibility gates as the daily cron).
     */
    public function applyNow(LateFeeService $service): RedirectResponse
    {
        $result = $service->processAllOverdueInvoices($this->getLandlordId());

        return back()->with('success', __('finance.late_fee.applied', ['count' => $result['fees_applied']]));
    }

    public function waive(WaiveLateFeeRequest $request, LateFee $lateFee, LateFeeService $service): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($lateFee->landlord_id !== $landlordId) {
            abort(403);
        }

        $service->waiveLateFee($lateFee, auth()->id(), $request->reason);

        return back()->with('success', 'Late fee waived successfully.');
    }

    public function waiveAll(WaiveLateFeeRequest $request, Invoice $invoice, LateFeeService $service): RedirectResponse
    {
        $landlordId = $this->getLandlordId();

        if ($invoice->landlord_id !== $landlordId) {
            abort(403);
        }

        $count = $service->waiveAllFeesForInvoice($invoice, auth()->id(), $request->reason);

        return back()->with('success', "Waived {$count} late fee(s) successfully.");
    }

    public function invoiceLateFees(Invoice $invoice): JsonResponse
    {
        $landlordId = $this->getLandlordId();

        if ($invoice->landlord_id !== $landlordId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoice->load(['lateFees.policy', 'lateFees.waivedByUser']);

        return $this->jsonWithCache([
            'late_fees' => $invoice->lateFees->map(fn ($fee) => [
                'id' => $fee->id,
                'fee_amount' => $fee->fee_amount,
                'cumulative_total' => $fee->cumulative_total,
                'applied_date' => $fee->applied_date->format('Y-m-d'),
                'days_overdue' => $fee->days_overdue,
                'is_waived' => $fee->is_waived,
                'waived_at' => $fee->waived_at?->format('Y-m-d H:i'),
                'waiver_reason' => $fee->waiver_reason,
                'waived_by' => $fee->waivedByUser?->name,
                'policy_name' => $fee->policy?->name,
            ])->toArray(),
            'total_active' => $invoice->late_fees_total,
            'total_waived' => $invoice->late_fees_waived,
        ], 60, 300);
    }

    private function getLateFeePolices(int $landlordId): array
    {
        return LateFeePolicy::where('landlord_id', $landlordId)
            ->with(['property:id,name', 'building:id,name'])
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'grace_period_days' => $p->grace_period_days,
                'fee_type' => $p->fee_type,
                'fee_percentage' => $p->fee_percentage,
                'fee_amount' => $p->fee_amount,
                'fee_description' => $p->getFeeDescription(),
                'is_compounding' => $p->is_compounding,
                'compounding_frequency' => $p->compounding_frequency,
                'max_fee_cap' => $p->max_fee_cap,
                'is_active' => $p->is_active,
                'scope_label' => $p->getScopeLabel(),
                'property_id' => $p->property_id,
                'building_id' => $p->building_id,
                'property_name' => $p->property?->name,
                'building_name' => $p->building?->name,
            ])
            ->toArray();
    }
}
