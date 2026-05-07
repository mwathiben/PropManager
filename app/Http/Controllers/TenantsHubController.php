<?php

namespace App\Http\Controllers;

use App\Enums\MoveOutStatus;
use App\Http\Traits\WithLandlordScope;
use App\Models\MoveOut;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\TenantVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantsHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $tab = $request->query('tab', 'directory');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['search', 'status', 'building_id']),
            'buildings' => $this->getBuildings($landlordId),
            'counts' => $this->getCounts($landlordId),
        ];

        $tabData = match ($tab) {
            'directory' => $this->getDirectoryData($request, $landlordId),
            'onboarding' => $this->getOnboardingData($request, $landlordId),
            'verifications' => $this->getVerificationsData($request, $landlordId),
            'payment-verifications' => $this->getPaymentVerificationsData($request, $landlordId),
            'move-outs' => $this->getMoveOutsData($request, $landlordId),
            'history' => $this->getHistoryData($request, $landlordId),
            default => $this->getDirectoryData($request, $landlordId),
        };

        return Inertia::render('Tenants/Hub', array_merge($baseProps, $tabData));
    }

    private function getDirectoryData(Request $request, int $landlordId): array
    {
        $search = $request->query('search', '');
        $buildingId = $request->query('building_id');

        $tenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->whereHas('leases', fn ($q) => $q->where('is_active', true))
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->when($buildingId, function ($q, $buildingId) {
                $q->whereHas('leases', fn ($lq) => $lq->where('is_active', true)
                    ->whereHas('unit', fn ($uq) => $uq->where('building_id', $buildingId)));
            })
            ->with([
                'leases' => fn ($q) => $q->where('is_active', true)->with('unit.building'),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'active_tenants' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereHas('leases', fn ($q) => $q->where('is_active', true))
                ->count(),
        ];

        return [
            'tenants' => $tenants,
            'stats' => $stats,
        ];
    }

    private function getOnboardingData(Request $request, int $landlordId): array
    {
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        $invitations = TenantInvitation::where('landlord_id', $landlordId)
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('tenant_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $status) => $q->where('status', $status))
            ->with('unit.building')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pending' => TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
            'accepted' => TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'accepted')
                ->count(),
            'expired' => TenantInvitation::where('landlord_id', $landlordId)
                ->where(function ($q) {
                    $q->where('status', 'expired')
                        ->orWhere(fn ($q2) => $q2->where('status', 'pending')->where('expires_at', '<=', now()));
                })
                ->count(),
        ];

        return [
            'invitations' => $invitations,
            'stats' => $stats,
        ];
    }

    private function getVerificationsData(Request $request, int $landlordId): array
    {
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        $verifications = TenantVerification::where('landlord_id', $landlordId)
            ->when($search, function ($q, $search) {
                $q->whereHas('lease.tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($status, fn ($q, $status) => $q->where('status', $status))
            ->with('lease.tenant')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return [
            'verifications' => $verifications,
        ];
    }

    private function getPaymentVerificationsData(Request $request, int $landlordId): array
    {
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        $paymentVerifications = TenantPaymentVerification::where('landlord_id', $landlordId)
            ->when($search, function ($q, $search) {
                $q->whereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
            })
            ->when($status, fn ($q, $status) => $q->where('status', $status))
            ->with(['tenant', 'unit'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return [
            'paymentVerifications' => $paymentVerifications,
        ];
    }

    private function getMoveOutsData(Request $request, int $landlordId): array
    {
        $status = $request->query('status', 'active');

        $query = MoveOut::where('landlord_id', $landlordId)
            ->with(['lease.tenant', 'lease.unit.building']);

        if ($status === 'active') {
            $query->active();
        } else {
            $query->completed();
        }

        $moveOuts = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

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

        return [
            'moveOuts' => $moveOuts,
            'stats' => $stats,
        ];
    }

    private function getHistoryData(Request $request, int $landlordId): array
    {
        $search = $request->query('search', '');
        $buildingId = $request->query('building_id');

        $pastTenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
            ->whereHas('leases')
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($buildingId, function ($q, $buildingId) {
                $q->whereHas('leases', fn ($lq) => $lq->whereHas('unit', fn ($uq) => $uq->where('building_id', $buildingId)));
            })
            ->with([
                'leases' => fn ($q) => $q->where('is_active', false)
                    ->orderBy('end_date', 'desc')
                    ->with(['unit.building', 'moveOut']),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($tenant) {
                $lastLease = $tenant->leases->first();
                $tenant->last_lease = $lastLease ? [
                    'unit_number' => $lastLease->unit?->unit_number,
                    'building_name' => $lastLease->unit?->building?->name,
                    'start_date' => $lastLease->start_date,
                    'end_date' => $lastLease->end_date,
                    'duration_months' => $lastLease->start_date && $lastLease->end_date
                        ? $lastLease->start_date->diffInMonths($lastLease->end_date)
                        : null,
                    'move_out' => $lastLease->moveOut ? [
                        'reason' => $lastLease->moveOut->reason,
                    ] : null,
                ] : null;

                return $tenant;
            });

        $stats = [
            'total_past_tenants' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
                ->whereHas('leases')
                ->count(),
        ];

        return [
            'pastTenants' => $pastTenants,
            'stats' => $stats,
        ];
    }

    private function getCounts(int $landlordId): array
    {
        return [
            'pendingInvitations' => TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
            'pendingVerifications' => TenantVerification::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->count(),
            'paymentVerifications' => TenantPaymentVerification::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->count(),
            'moveOuts' => MoveOut::where('landlord_id', $landlordId)
                ->active()
                ->count(),
        ];
    }
}
