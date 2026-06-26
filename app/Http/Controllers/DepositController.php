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

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->where('deposit_amount', '>', 0)
            ->with(['tenant:id,name,email,mobile_number', 'unit.building:id,name']);

        $this->applySearchFilter($query, $request);
        $this->applyBuildingFilter($query, $request);
        $this->applySorting($query, $request);

        $leases = $query->paginate(20)->withQueryString();

        $stats = $this->calculateStats($landlordId);

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

    private function applySearchFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = $request->search;
        $query->whereHas('tenant', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })->orWhereHas('unit', function ($q) use ($search) {
            $q->where('unit_number', 'like', "%{$search}%");
        });
    }

    private function applyBuildingFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        if (! $request->filled('building_id')) {
            return;
        }

        $query->whereHas('unit', function ($q) use ($request) {
            $q->where('building_id', $request->building_id);
        });
    }

    private function applySorting(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
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
    }

    /**
     * @return array{total_deposits: mixed, total_leases: int, total_wallet_balance: mixed}
     */
    private function calculateStats(int $landlordId): array
    {
        return [
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
    }
}
