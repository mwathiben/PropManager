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
            ->with(['unit.building', 'rentHistory', 'documents'])
            ->first();

        if (! $lease) {
            return Inertia::render('Tenant/Lease', [
                'hasLease' => false,
                'lease' => null,
            ]);
        }

        return Inertia::render('Tenant/Lease', [
            'hasLease' => true,
            'lease' => $lease,
            'unit' => $lease->unit,
            'building' => $lease->unit->building,
            'rentHistory' => $lease->rentHistory()->orderBy('effective_date', 'desc')->get(),
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

        $readings = $lease
            ? \App\Models\WaterReading::query()
                ->where('unit_id', $lease->unit_id)
                ->approved()
                ->orderBy('reading_date', 'desc')
                ->limit(36)
                ->get(['id', 'reading_date', 'consumption', 'cost', 'status'])
            : collect();

        return Inertia::render('Tenant/Water', [
            'hasUnit' => (bool) $lease,
            'readings' => $readings,
        ]);
    }
}
