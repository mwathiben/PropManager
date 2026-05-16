<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\LeaseRenewal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-29 WF-LEASE-RENEW-3: tenant accepts or rejects a proposed
 * lease renewal. The landlord still needs to confirm before the
 * parent Lease.end_date + rent_amount are mutated (that's the
 * landlord-side WF-LEASE-RENEW-2 controller).
 */
class RenewalResponseController extends Controller
{
    public function accept(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $this->guard($request, $renewal);

        $renewal->update([
            'status' => LeaseRenewal::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        return Redirect::route('tenant.finances.index')
            ->with('success', __('workflow.lease_renewal.tenant_accepted'));
    }

    public function reject(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->guard($request, $renewal);

        $renewal->update([
            'status' => LeaseRenewal::STATUS_REJECTED,
            'responded_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return Redirect::route('tenant.finances.index')
            ->with('success', __('workflow.lease_renewal.tenant_rejected'));
    }

    private function guard(Request $request, LeaseRenewal $renewal): void
    {
        $tenant = $request->user();
        $lease = $renewal->lease;

        abort_unless(
            $lease && $lease->tenant_id === $tenant->id,
            403,
            'You can only respond to renewals on your own lease.',
        );

        abort_unless(
            $renewal->status === LeaseRenewal::STATUS_PROPOSED,
            422,
            'This renewal is no longer in a respondable state.',
        );
    }
}
