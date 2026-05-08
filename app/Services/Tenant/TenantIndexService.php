<?php

namespace App\Services\Tenant;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class TenantIndexService
{
    public function getDataForTab(string $tab, int $landlordId, Request $request): ?LengthAwarePaginator
    {
        $search = $request->get('search', '');

        return match ($tab) {
            'active' => $this->getActiveTenants($landlordId, $search),
            'past' => $this->getPastTenants($landlordId, $search),
            'pending' => $this->getPendingInvitations($landlordId, $search),
            default => null,
        };
    }

    public function getCounts(int $landlordId): array
    {
        return [
            'active' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereHas('leases', fn ($q) => $q->where('is_active', true))
                ->count(),
            'pending' => TenantInvitation::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
            'past' => User::where('role', 'tenant')
                ->where('landlord_id', $landlordId)
                ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
                ->whereHas('leases')
                ->count(),
        ];
    }

    public function getStats(int $landlordId, array $counts): array
    {
        return [
            'totalTenants' => $counts['active'] + $counts['past'],
            'activeTenants' => $counts['active'],
            'pendingInvitations' => $counts['pending'],
            'withArrears' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->whereHas('lease', fn ($q) => $q->where('is_active', true))
                ->distinct('lease_id')
                ->count('lease_id'),
            'totalMonthlyRent' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->sum('rent_amount'),
            'totalArrears' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereHas('lease', fn ($q) => $q->where('is_active', true))
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
        ];
    }

    private function getActiveTenants(int $landlordId, string $search): LengthAwarePaginator
    {
        return $this->baseTenantQuery($landlordId, $search)
            ->whereHas('leases', fn ($q) => $q->where('is_active', true))
            ->with([
                'leases' => fn ($q) => $q->where('is_active', true)->with('unit.building.property'),
                'emergencyContacts',
            ])
            ->withCount(['tenantNotes', 'activities'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    private function getPastTenants(int $landlordId, string $search): LengthAwarePaginator
    {
        return $this->baseTenantQuery($landlordId, $search)
            ->whereDoesntHave('leases', fn ($q) => $q->where('is_active', true))
            ->whereHas('leases')
            ->with([
                'leases' => fn ($q) => $q->where('is_active', false)
                    ->orderBy('end_date', 'desc')
                    ->with('unit.building.property'),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    private function getPendingInvitations(int $landlordId, string $search): LengthAwarePaginator
    {
        return TenantInvitation::where('landlord_id', $landlordId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('tenant_name', 'like', "%{$search}%")
                        ->orWhere('tenant_phone', 'like', "%{$search}%");
                });
            })
            ->with('unit.building.property')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    private function baseTenantQuery(int $landlordId, string $search)
    {
        return User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            });
    }
}
