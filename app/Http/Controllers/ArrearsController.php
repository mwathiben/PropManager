<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\TenantClock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ArrearsController extends Controller
{
    /**
     * Display a listing of all arrears (overdue invoices).
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isScopeOwner() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
            ->whereRaw('(total_due - amount_paid) > 0')
            ->with([
                'lease.tenant:id,name,email,mobile_number',
                'lease.unit.building:id,name',
            ]);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Building filter
        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
        }

        // Age filter (days overdue)
        // Phase-21 DEFER-PERF-2: anchor "today" in the requesting user's
        // timezone so the 0-30 / 31-60 / 61-90 buckets honor day-boundary
        // expectations across TZs (Phase-17 TenantClock pattern).
        if ($request->filled('age')) {
            $today = TenantClock::nowFor($user)->startOfDay();
            switch ($request->age) {
                case '0-30':
                    $query->where('due_date', '>=', $today->copy()->subDays(30))
                        ->where('due_date', '<', $today);
                    break;
                case '31-60':
                    $query->where('due_date', '>=', $today->copy()->subDays(60))
                        ->where('due_date', '<', $today->copy()->subDays(30));
                    break;
                case '61-90':
                    $query->where('due_date', '>=', $today->copy()->subDays(90))
                        ->where('due_date', '<', $today->copy()->subDays(60));
                    break;
                case '90+':
                    $query->where('due_date', '<', $today->copy()->subDays(90));
                    break;
            }
        }

        $allowedSorts = ['due_date', 'total_due', 'amount_paid', 'created_at', 'invoice_number'];
        $sortField = in_array($request->get('sort'), $allowedSorts, true) ? $request->get('sort') : 'due_date';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDirection);

        $invoices = $query->paginate(20)->withQueryString();

        // Add computed fields. Phase-21 DEFER-PERF-2: days_overdue
        // computed against user-TZ today, not server-TZ today.
        $userToday = TenantClock::nowFor($user)->startOfDay();
        $invoices->getCollection()->transform(function ($invoice) use ($userToday) {
            $invoice->amount_owed = $invoice->total_due - $invoice->amount_paid;
            $invoice->days_overdue = Carbon::parse($invoice->due_date)->diffInDays($userToday);

            return $invoice;
        });

        // PERF-Q1: collapse 7 separate aggregate queries into a single
        // CASE WHEN selectRaw. The pre-fix code re-scanned the same
        // arrears predicate seven times to produce $stats + $aging.
        // Phase-21 DEFER-PERF-2: aging cutoffs anchored in user TZ.
        $today = $userToday;
        $cutoff30 = $today->copy()->subDays(30)->format('Y-m-d');
        $cutoff60 = $today->copy()->subDays(60)->format('Y-m-d');
        $cutoff90 = $today->copy()->subDays(90)->format('Y-m-d');
        $todayStr = $today->format('Y-m-d');

        $agg = Invoice::where('landlord_id', $landlordId)
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
            ->whereRaw('(total_due - amount_paid) > 0')
            ->selectRaw('
                COALESCE(SUM(total_due - amount_paid), 0) as total_arrears,
                COUNT(DISTINCT lease_id) as tenants_in_arrears,
                COUNT(*) as invoices_overdue,
                COALESCE(SUM(CASE WHEN due_date >= ? AND due_date < ? THEN total_due - amount_paid ELSE 0 END), 0) as age_0_30,
                COALESCE(SUM(CASE WHEN due_date >= ? AND due_date < ? THEN total_due - amount_paid ELSE 0 END), 0) as age_31_60,
                COALESCE(SUM(CASE WHEN due_date >= ? AND due_date < ? THEN total_due - amount_paid ELSE 0 END), 0) as age_61_90,
                COALESCE(SUM(CASE WHEN due_date <  ? THEN total_due - amount_paid ELSE 0 END), 0) as age_90_plus
            ', [
                $cutoff30, $todayStr,
                $cutoff60, $cutoff30,
                $cutoff90, $cutoff60,
                $cutoff90,
            ])
            ->first();

        $stats = [
            'total_arrears' => (float) ($agg->total_arrears ?? 0),
            'tenants_in_arrears' => (int) ($agg->tenants_in_arrears ?? 0),
            'invoices_overdue' => (int) ($agg->invoices_overdue ?? 0),
        ];

        $aging = [
            '0_30' => (float) ($agg->age_0_30 ?? 0),
            '31_60' => (float) ($agg->age_31_60 ?? 0),
            '61_90' => (float) ($agg->age_61_90 ?? 0),
            '90_plus' => (float) ($agg->age_90_plus ?? 0),
        ];

        // Get buildings for filter dropdown
        $buildings = \App\Models\Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Arrears/Index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'aging' => $aging,
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'building_id', 'age', 'sort', 'direction']),
        ]);
    }
}
