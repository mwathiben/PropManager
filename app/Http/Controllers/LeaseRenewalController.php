<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\LeaseRenewal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-29 WF-LEASE-RENEW-2: landlord-side renewal initiate + confirm.
 *
 * store(): landlord proposes new terms on a lease they own (creates
 *   LeaseRenewal status=proposed)
 * confirm(): landlord finalises an accepted renewal (writes new
 *   end_date + rent_amount on the parent Lease atomically and
 *   advances status=confirmed)
 *
 * Tenant accept/reject lives in Tenant\RenewalResponseController.
 */
class LeaseRenewalController extends Controller
{
    public function store(Request $request, Lease $lease): RedirectResponse
    {
        abort_unless(
            $request->user()->isScopeOwner() && $lease->landlord_id === $request->user()->id,
            403,
            'Only the lease landlord can propose a renewal.',
        );

        $hasOpen = LeaseRenewal::where('lease_id', $lease->id)
            ->whereIn('status', LeaseRenewal::OPEN_STATUSES)
            ->exists();
        abort_if($hasOpen, 422, 'An open renewal already exists for this lease.');

        $data = $request->validate([
            'proposed_end_date' => ['required', 'date', 'after:today'],
            'proposed_rent_amount_cents' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        LeaseRenewal::create([
            'landlord_id' => $lease->landlord_id,
            'lease_id' => $lease->id,
            'proposed_end_date' => $data['proposed_end_date'],
            'proposed_rent_amount_cents' => $data['proposed_rent_amount_cents'],
            'status' => LeaseRenewal::STATUS_PROPOSED,
            'notes' => $data['notes'] ?? null,
            'proposed_at' => now(),
        ]);

        return Redirect::back()->with('success', __('workflow.lease_renewal.proposed'));
    }

    public function confirm(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        abort_unless(
            $request->user()->isScopeOwner() && $renewal->landlord_id === $request->user()->id,
            403,
            'Only the lease landlord can confirm a renewal.',
        );
        abort_unless(
            $renewal->status === LeaseRenewal::STATUS_ACCEPTED,
            422,
            'Only accepted renewals can be confirmed.',
        );

        DB::transaction(function () use ($renewal) {
            $lease = $renewal->lease;
            $lease->update([
                'end_date' => $renewal->proposed_end_date,
                'rent_amount' => $renewal->proposed_rent_amount_cents / 100,
            ]);
            $renewal->update([
                'status' => LeaseRenewal::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        });

        return Redirect::back()->with('success', __('workflow.lease_renewal.confirmed'));
    }
}
