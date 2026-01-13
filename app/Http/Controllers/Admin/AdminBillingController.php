<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingSetting;
use App\Models\PlatformFee;
use App\Services\BillingModelService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminBillingController extends Controller
{
    protected BillingModelService $billingService;

    public function __construct(BillingModelService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Display billing settings page
     */
    public function index(): Response
    {
        $settings = $this->billingService->getActiveBillingModel();
        $recentChanges = $this->billingService->getChangeHistory(10);

        // Get current month revenue
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $monthlyAnalytics = $this->billingService->getRevenueAnalytics($startOfMonth, $endOfMonth);

        return Inertia::render('Admin/BillingSettings', [
            'settings' => [
                'id' => $settings->id,
                'active_billing_model' => $settings->active_billing_model,
                'billing_model_label' => $settings->billing_model_label,
                'transaction_fee_percentage' => $settings->transaction_fee_percentage,
                'minimum_fee' => $settings->minimum_fee,
                'maximum_fee' => $settings->maximum_fee,
                'fee_bearer' => $settings->fee_bearer,
                'fee_bearer_label' => $settings->fee_bearer_label,
                'hybrid_subscription_discount' => $settings->hybrid_subscription_discount,
                'is_active' => $settings->is_active,
                'updated_at' => $settings->updated_at?->format('M d, Y H:i'),
            ],
            'billingModels' => PlatformBillingSetting::BILLING_MODELS,
            'feeBearers' => PlatformBillingSetting::FEE_BEARERS,
            'recentChanges' => $recentChanges->map(function ($change) {
                return [
                    'id' => $change->id,
                    'from_model' => $change->from_model,
                    'from_model_label' => $change->from_model_label,
                    'to_model' => $change->to_model,
                    'to_model_label' => $change->to_model_label,
                    'description' => $change->description,
                    'reason' => $change->reason,
                    'changed_by' => $change->changedByUser?->name ?? 'System',
                    'effective_date' => $change->effective_date->format('M d, Y H:i'),
                    'created_at' => $change->created_at->format('M d, Y H:i'),
                ];
            }),
            'monthlyAnalytics' => $monthlyAnalytics,
        ]);
    }

    /**
     * Switch billing model
     */
    public function switchModel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'billing_model' => 'required|in:transaction_fee,subscription,hybrid',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->billingService->switchBillingModel(
                $request->billing_model,
                auth()->user(),
                $request->reason
            );

            return redirect()->back()->with('success', 'Billing model updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update fee settings
     */
    public function updateFees(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'transaction_fee_percentage' => 'required|numeric|min:0|max:100',
            'minimum_fee' => 'required|numeric|min:0',
            'maximum_fee' => 'nullable|numeric|min:0',
            'fee_bearer' => 'required|in:landlord,platform,shared',
            'hybrid_subscription_discount' => 'required|numeric|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        // Validate maximum > minimum if set
        if ($request->maximum_fee && $request->maximum_fee < $request->minimum_fee) {
            return redirect()->back()->withErrors(['maximum_fee' => 'Maximum fee must be greater than minimum fee.']);
        }

        try {
            $this->billingService->updateSettings([
                'transaction_fee_percentage' => $request->transaction_fee_percentage,
                'minimum_fee' => $request->minimum_fee,
                'maximum_fee' => $request->maximum_fee,
                'fee_bearer' => $request->fee_bearer,
                'hybrid_subscription_discount' => $request->hybrid_subscription_discount,
                'reason' => $request->reason,
            ], auth()->user());

            return redirect()->back()->with('success', 'Fee settings updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get revenue analytics
     */
    public function analytics(Request $request): Response
    {
        $startDate = $request->get('start_date')
            ? new \DateTime($request->get('start_date'))
            : now()->subDays(30);

        $endDate = $request->get('end_date')
            ? new \DateTime($request->get('end_date'))
            : now();

        $analytics = $this->billingService->getRevenueAnalytics($startDate, $endDate);

        // Get top landlords by fees
        $topLandlords = PlatformFee::whereIn('status', ['collected', 'settled'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('landlord_id, SUM(fee_amount) as total_fees, COUNT(*) as transactions')
            ->groupBy('landlord_id')
            ->orderByDesc('total_fees')
            ->limit(10)
            ->with('landlord:id,name,email')
            ->get();

        return Inertia::render('Admin/BillingAnalytics', [
            'analytics' => $analytics,
            'topLandlords' => $topLandlords->map(function ($item) {
                return [
                    'landlord_id' => $item->landlord_id,
                    'landlord_name' => $item->landlord?->name ?? 'Unknown',
                    'landlord_email' => $item->landlord?->email ?? '',
                    'total_fees' => round($item->total_fees, 2),
                    'transactions' => $item->transactions,
                ];
            }),
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Get billing model change history
     */
    public function history(Request $request): Response
    {
        $limit = $request->get('limit', 50);
        $changes = $this->billingService->getChangeHistory($limit);

        return Inertia::render('Admin/BillingHistory', [
            'changes' => $changes->map(function ($change) {
                return [
                    'id' => $change->id,
                    'from_model' => $change->from_model,
                    'from_model_label' => $change->from_model_label,
                    'to_model' => $change->to_model,
                    'to_model_label' => $change->to_model_label,
                    'description' => $change->description,
                    'reason' => $change->reason,
                    'changed_by' => $change->changedByUser?->name ?? 'System',
                    'effective_date' => $change->effective_date->format('M d, Y H:i'),
                    'settings_snapshot' => $change->settings_snapshot,
                    'created_at' => $change->created_at->format('M d, Y H:i'),
                ];
            }),
        ]);
    }

    /**
     * Preview fee calculation
     */
    public function previewFee(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $settings = $this->billingService->getActiveBillingModel();
        $preview = $settings->calculateFeePreview($request->amount);

        return response()->json([
            'status' => 'success',
            'preview' => $preview,
        ]);
    }
}
