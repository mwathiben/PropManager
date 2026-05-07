<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepositController extends Controller
{
    /**
     * Display a listing of all deposits (security deposits from active leases).
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->where('deposit_amount', '>', 0)
            ->with(['tenant:id,name,email,mobile_number', 'unit.building:id,name']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('tenant', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('unit', function ($q) use ($search) {
                $q->where('unit_number', 'like', "%{$search}%");
            });
        }

        // Building filter
        if ($request->filled('building_id')) {
            $query->whereHas('unit', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
        }

        $allowedSorts = ['deposit_amount', 'deposit_status', 'created_at', 'start_date', 'tenant'];
        $sortField = in_array($request->get('sort'), $allowedSorts, true) ? $request->get('sort') : 'deposit_amount';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';

        if ($sortField === 'tenant') {
            $query->join('users', 'leases.tenant_id', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('leases.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $leases = $query->paginate(20)->withQueryString();

        // Calculate stats
        $stats = [
            'total_deposits' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->sum('deposit_amount'),
            'total_leases' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->where('deposit_amount', '>', 0)
                ->count(),
            'total_wallet_balance' => Lease::where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->sum('wallet_balance'),
        ];

        // Get buildings for filter dropdown
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Deposits/Index', [
            'leases' => $leases,
            'stats' => $stats,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'building_id', 'sort', 'direction']),
        ]);
    }
}
