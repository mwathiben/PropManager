<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Lease;
use App\Models\LeaseCoTenant;
use App\Services\Lease\LeaseCoTenantService;
use Illuminate\Http\Request;

/**
 * Phase-83 CO-TENANT-2: landlord/caretaker management of co-tenants on a lease,
 * surfaced on the lease lifecycle view.
 */
class LeaseCoTenantController extends Controller
{
    use WithLandlordScope;

    public function store(Request $request, Lease $lease, LeaseCoTenantService $service)
    {
        abort_unless((int) $lease->landlord_id === $this->getLandlordId(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'is_responsible_for_rent' => ['nullable', 'boolean'],
            'liability_share' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $service->add($lease, $validated);

        return back()->with('success', __('lease.co_tenant.added'));
    }

    public function destroy(LeaseCoTenant $coTenant, LeaseCoTenantService $service)
    {
        $this->authorize('delete', $coTenant);

        $service->remove($coTenant);

        return back()->with('success', __('lease.co_tenant.removed'));
    }
}
