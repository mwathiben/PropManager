<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
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

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
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
        if ($request->filled('age')) {
            $today = Carbon::today();
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

        // Add computed fields
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->amount_owed = $invoice->total_due - $invoice->amount_paid;
            $invoice->days_overdue = Carbon::parse($invoice->due_date)->diffInDays(Carbon::today());

            return $invoice;
        });

        // Calculate stats
        $stats = [
            'total_arrears' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
            'tenants_in_arrears' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->distinct('lease_id')
                ->count('lease_id'),
            'invoices_overdue' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->count(),
        ];

        // Calculate aging buckets
        $today = Carbon::today();
        $aging = [
            '0_30' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->where('due_date', '>=', $today->copy()->subDays(30))
                ->where('due_date', '<', $today)
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
            '31_60' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->where('due_date', '>=', $today->copy()->subDays(60))
                ->where('due_date', '<', $today->copy()->subDays(30))
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
            '61_90' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->where('due_date', '>=', $today->copy()->subDays(90))
                ->where('due_date', '<', $today->copy()->subDays(60))
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
            '90_plus' => Invoice::where('landlord_id', $landlordId)
                ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
                ->whereRaw('(total_due - amount_paid) > 0')
                ->where('due_date', '<', $today->copy()->subDays(90))
                ->selectRaw('COALESCE(SUM(total_due - amount_paid), 0) as total')
                ->value('total') ?? 0,
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
