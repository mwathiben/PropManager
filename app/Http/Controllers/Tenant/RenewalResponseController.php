<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\LeaseRenewalCounterHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-29 WF-LEASE-RENEW-3: tenant accepts or rejects a proposed
 * lease renewal. The landlord still needs to confirm before the
 * parent Lease.end_date + rent_amount are mutated (that's the
 * landlord-side WF-LEASE-RENEW-2 controller).
 */
class RenewalResponseController extends Controller
{
    /**
     * Phase-84 RENEWAL-RESPONSE-1: dedicated tenant page to review the open
     * renewal offer (current vs proposed) and accept / reject / counter — the
     * routes existed but had no page; a tenant could only act from a notification.
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user();
        $lease = $tenant->lease()->with(['unit.building'])->first();
        $renewal = $this->resolveOpenRenewal($lease);
        $offerDoc = $this->resolveOfferDocument($lease, $renewal);

        return Inertia::render('Tenant/Renewals', [
            'hasLease' => (bool) $lease,
            'lease' => $this->leaseData($lease),
            'renewal' => $this->renewalData($renewal),
            'offerDocumentId' => $offerDoc?->id,
        ]);
    }

    private function resolveOpenRenewal(?Lease $lease): ?LeaseRenewal
    {
        if (! $lease) {
            return null;
        }

        return LeaseRenewal::where('lease_id', $lease->id)
            ->whereIn('status', LeaseRenewal::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    // Phase-83 generated renewal-offer PDF for this lease, if one exists.
    private function resolveOfferDocument(?Lease $lease, ?LeaseRenewal $renewal): ?Document
    {
        if (! $lease || ! $renewal) {
            return null;
        }

        return Document::where('documentable_type', Lease::class)
            ->where('documentable_id', $lease->id)
            ->where('document_type', 'notice')
            ->where('title', __('lease_doc.renewal.title'))
            ->latest('id')
            ->first();
    }

    private function leaseData(?Lease $lease): ?array
    {
        if (! $lease) {
            return null;
        }

        return [
            'rent_amount' => (float) $lease->rent_amount,
            'end_date' => $lease->end_date?->toDateString(),
            'unit' => $lease->unit?->unit_number,
            'building' => $lease->unit?->building?->name,
        ];
    }

    private function renewalData(?LeaseRenewal $renewal): ?array
    {
        if (! $renewal) {
            return null;
        }

        return [
            'id' => $renewal->id,
            'status' => $renewal->status,
            'proposed_rent' => $renewal->proposed_rent_amount_cents / 100,
            'proposed_end_date' => $renewal->proposed_end_date?->toDateString(),
            'notes' => $renewal->notes,
            'counter_rent' => $renewal->counter_rent_amount_cents ? $renewal->counter_rent_amount_cents / 100 : null,
            'counter_end_date' => $renewal->counter_end_date?->toDateString(),
            'counter_message' => $renewal->counter_message,
            'can_respond' => $renewal->status === LeaseRenewal::STATUS_PROPOSED,
        ];
    }

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

        DB::transaction(function () use ($renewal, $request, $data): void {
            $renewal->update([
                'status' => LeaseRenewal::STATUS_REJECTED,
                'responded_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            LeaseRenewalCounterHistory::create([
                'lease_renewal_id' => $renewal->id,
                'actor_user_id' => $request->user()->id,
                'action' => LeaseRenewalCounterHistory::ACTION_REJECTED,
                'rent_amount_cents' => $renewal->proposed_rent_amount_cents,
                'end_date' => $renewal->proposed_end_date,
                'message' => $data['rejection_reason'] ?? null,
            ]);
        });

        return Redirect::route('tenant.finances.index')
            ->with('success', __('workflow.lease_renewal.tenant_rejected'));
    }

    /**
     * Phase-45 LEASE-COUNTER-1: tenant submits a counter-offer with
     * alternative rent + end_date + optional message. The renewal
     * transitions proposed → counter_proposed; the landlord-side
     * controller owns the next decision.
     */
    public function counter(Request $request, LeaseRenewal $renewal): RedirectResponse
    {
        $data = $request->validate([
            'counter_rent_amount_cents' => ['required', 'integer', 'min:1'],
            'counter_end_date' => ['required', 'date', 'after:today'],
            'counter_message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->guard($request, $renewal);

        DB::transaction(function () use ($renewal, $request, $data): void {
            $renewal->update([
                'status' => LeaseRenewal::STATUS_COUNTER_PROPOSED,
                'counter_rent_amount_cents' => $data['counter_rent_amount_cents'],
                'counter_end_date' => $data['counter_end_date'],
                'counter_message' => $data['counter_message'] ?? null,
                'counter_submitted_at' => now(),
                'responded_at' => now(),
            ]);

            LeaseRenewalCounterHistory::create([
                'lease_renewal_id' => $renewal->id,
                'actor_user_id' => $request->user()->id,
                'action' => LeaseRenewalCounterHistory::ACTION_COUNTERED,
                'rent_amount_cents' => $data['counter_rent_amount_cents'],
                'end_date' => $data['counter_end_date'],
                'message' => $data['counter_message'] ?? null,
            ]);
        });

        return Redirect::route('tenant.finances.index')
            ->with('success', __('workflow.lease_renewal.tenant_countered'));
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
