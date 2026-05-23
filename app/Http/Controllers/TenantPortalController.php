<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Inertia\Inertia;

class TenantPortalController extends Controller
{
    public function payments()
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $lease = $user->lease;

        if (! $lease) {
            return Inertia::render('Tenant/Payments', [
                'hasLease' => false,
                'payments' => [],
                'invoices' => [],
            ]);
        }

        $payments = Payment::where('lease_id', $lease->id)
            ->with('invoice')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $invoices = Invoice::where('lease_id', $lease->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPaid = Payment::withArchived()->where('lease_id', $lease->id)->sum('amount');
        $totalInvoiced = Invoice::where('lease_id', $lease->id)->sum('total_due');
        $balance = $totalPaid - $totalInvoiced;

        return Inertia::render('Tenant/Payments', [
            'hasLease' => true,
            'payments' => $payments,
            'invoices' => $invoices,
            'totalPaid' => $totalPaid,
            'totalInvoiced' => $totalInvoiced,
            'balance' => $balance,
        ]);
    }

    public function lease()
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $lease = $user->lease()
            ->with(['unit.building', 'rentHistory', 'documents', 'coTenants', 'guarantors'])
            ->first();

        if (! $lease) {
            return Inertia::render('Tenant/Lease', [
                'hasLease' => false,
                'lease' => null,
            ]);
        }

        // Phase-84 LEASE-VISIBILITY: surface the Phase-83 co-tenants / guarantors /
        // open renewal / generated lease agreement to the tenant (read-only).
        $activeRenewal = \App\Models\LeaseRenewal::where('lease_id', $lease->id)
            ->whereIn('status', \App\Models\LeaseRenewal::OPEN_STATUSES)
            ->latest('id')
            ->first();

        $leaseAgreement = $lease->documents->firstWhere('document_type', 'lease_agreement');

        return Inertia::render('Tenant/Lease', [
            'hasLease' => true,
            'lease' => $lease,
            'unit' => $lease->unit,
            'building' => $lease->unit->building,
            'rentHistory' => $lease->rentHistory()->orderBy('effective_date', 'desc')->get(),
            'coTenants' => $lease->coTenants->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'relationship' => $c->relationship,
                'is_responsible_for_rent' => $c->is_responsible_for_rent,
            ])->values(),
            'guarantors' => $lease->guarantors->where('status', 'active')->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'relationship' => $g->relationship,
            ])->values(),
            'activeRenewal' => $activeRenewal ? [
                'id' => $activeRenewal->id,
                'status' => $activeRenewal->status,
            ] : null,
            'leaseAgreementId' => $leaseAgreement?->id,
        ]);
    }

    /**
     * Phase-79 WATER-GATE-4: read-only water view for the tenant — the meter
     * readings + charges for the unit they rent. Reachable only when the
     * landlord charges for water (water.module middleware gates the route).
     */
    public function water()
    {
        $user = auth()->user();

        if (! $user->isTenant()) {
            abort(403, 'This page is only for tenants.');
        }

        $lease = $user->lease()->with('unit')->first();

        // Phase-93: bound everything to THIS tenancy's window — water_readings has
        // no lease_id, so without the floor a new tenant would see the previous
        // occupant's history for the same unit (reviewer CRITICAL).
        $since = $lease?->start_date?->toDateString();

        $readings = $lease
            ? \App\Models\WaterReading::query()
                ->where('unit_id', $lease->unit_id)
                ->approved()
                ->when($since, fn ($q) => $q->where('reading_date', '>=', $since))
                ->orderBy('reading_date', 'desc')
                ->limit(12)
                ->get(['id', 'reading_date', 'consumption', 'cost', 'status'])
            : collect();

        // Phase-93: consumption history + summary + leak self-alert + charge history
        // + disconnection, from the shared (unit-centric) account service the
        // water-client dashboard reuses.
        $account = $lease
            ? app(\App\Services\Water\WaterAccountService::class)->overview($lease->unit_id, $lease->id, $since)
            : ['history' => [], 'summary' => null, 'alert' => null, 'charges' => [], 'disconnection' => ['disconnected' => false, 'reason' => null]];

        return Inertia::render('Tenant/Water', [
            'hasUnit' => (bool) $lease,
            'readings' => $readings,
            'history' => $account['history'],
            'summary' => $account['summary'],
            'alert' => $account['alert'],
            'charges' => $account['charges'],
            'meterDisconnected' => $account['disconnection']['disconnected'],
            'disconnectReason' => $account['disconnection']['reason'],
            'payUrl' => route('tenant.payments'),
        ]);
    }
}
