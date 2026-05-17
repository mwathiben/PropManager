<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LeaseRenewal;
use App\Models\LeaseRenewalCounterHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-45 LEASE-COUNTER-2: landlord-side review of a tenant counter-offer.
 * Three actions: accept the counter (write counter_* into proposed_*
 * + status=accepted), reject (status=rejected), re-propose (status=proposed
 * + new proposed_* values; tenant gets a fresh accept/reject/counter cycle).
 *
 * Authorisation: actor must be the landlord/caretaker on the renewal's
 * landlord_id, or super_admin.
 */
class LeaseRenewalCounterReviewController extends Controller
{
    public function accept(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $this->guard($request, $renewal);

        DB::transaction(function () use ($renewal, $request): void {
            // Promote counter values into the canonical proposed columns,
            // then accept.
            $renewal->update([
                'proposed_rent_amount_cents' => $renewal->counter_rent_amount_cents,
                'proposed_end_date' => $renewal->counter_end_date,
                'status' => LeaseRenewal::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            LeaseRenewalCounterHistory::create([
                'lease_renewal_id' => $renewal->id,
                'actor_user_id' => $request->user()->id,
                'action' => LeaseRenewalCounterHistory::ACTION_ACCEPTED,
                'rent_amount_cents' => $renewal->proposed_rent_amount_cents,
                'end_date' => $renewal->proposed_end_date,
                'message' => null,
            ]);
        });

        return Redirect::back()->with('success', __('workflow.lease_renewal.counter_accepted'));
    }

    public function reject(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->guard($request, $renewal);

        DB::transaction(function () use ($renewal, $request, $data): void {
            $renewal->update([
                'status' => LeaseRenewal::STATUS_REJECTED,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'responded_at' => now(),
            ]);

            LeaseRenewalCounterHistory::create([
                'lease_renewal_id' => $renewal->id,
                'actor_user_id' => $request->user()->id,
                'action' => LeaseRenewalCounterHistory::ACTION_REJECTED,
                'rent_amount_cents' => $renewal->counter_rent_amount_cents,
                'end_date' => $renewal->counter_end_date,
                'message' => $data['rejection_reason'] ?? null,
            ]);
        });

        return Redirect::back()->with('success', __('workflow.lease_renewal.counter_rejected'));
    }

    public function rePropose(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'proposed_rent_amount_cents' => ['required', 'integer', 'min:1'],
            'proposed_end_date' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->guard($request, $renewal);

        DB::transaction(function () use ($renewal, $request, $data): void {
            $renewal->update([
                'proposed_rent_amount_cents' => $data['proposed_rent_amount_cents'],
                'proposed_end_date' => $data['proposed_end_date'],
                'notes' => $data['notes'] ?? $renewal->notes,
                'status' => LeaseRenewal::STATUS_PROPOSED,
                // Clear counter columns — the tenant gets a fresh
                // accept/reject/counter cycle on the new terms.
                'counter_rent_amount_cents' => null,
                'counter_end_date' => null,
                'counter_message' => null,
                'counter_submitted_at' => null,
                'responded_at' => null,
            ]);

            LeaseRenewalCounterHistory::create([
                'lease_renewal_id' => $renewal->id,
                'actor_user_id' => $request->user()->id,
                'action' => LeaseRenewalCounterHistory::ACTION_RE_PROPOSED,
                'rent_amount_cents' => $data['proposed_rent_amount_cents'],
                'end_date' => $data['proposed_end_date'],
                'message' => $data['notes'] ?? null,
            ]);
        });

        return Redirect::back()->with('success', __('workflow.lease_renewal.counter_re_proposed'));
    }

    private function guard(Request $request, LeaseRenewal $renewal): void
    {
        $user = $request->user();

        $isOwner = $user->isSuperAdmin()
            || $renewal->landlord_id === $user->id
            || $renewal->landlord_id === $user->landlord_id;

        abort_unless(
            $isOwner,
            403,
            'You may not review counter-offers on this renewal.',
        );

        abort_unless(
            $renewal->status === LeaseRenewal::STATUS_COUNTER_PROPOSED,
            422,
            'This renewal is not currently counter-proposed.',
        );
    }
}
