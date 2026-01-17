<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function occupancy(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $units = Unit::where('landlord_id', $landlordId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = $units->sum();

        return response()->json([
            'total_units' => $total,
            'occupied' => $units->get('occupied', 0),
            'vacant' => $units->get('vacant', 0),
            'maintenance' => $units->get('maintenance', 0),
            'arrears' => $units->get('arrears', 0),
            'occupancy_rate' => $total > 0 ? round(($units->get('occupied', 0) / $total) * 100, 1) : 0,
        ]);
    }

    public function revenue(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->toDateString());

        $payments = Payment::where('landlord_id', $landlordId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('SUM(amount) as total, COUNT(*) as count, payment_method')
            ->groupBy('payment_method')
            ->get();

        $total = $payments->sum('total');
        $transactionCount = $payments->sum('count');

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_revenue' => (float) $total,
            'by_method' => $payments->mapWithKeys(fn ($p) => [$p->payment_method => (float) $p->total]),
            'transaction_count' => (int) $transactionCount,
        ]);
    }

    public function arrears(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $overdueInvoices = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->with(['lease.tenant:id,name,email,mobile_number', 'lease.unit:id,unit_number,building_id', 'lease.unit.building:id,name'])
            ->get();

        $now = Carbon::now();

        $aged = [
            '0_30' => [],
            '31_60' => [],
            '61_90' => [],
            '90_plus' => [],
        ];

        foreach ($overdueInvoices as $invoice) {
            $daysPastDue = $invoice->due_date->diffInDays($now);
            $balance = $invoice->total_due - $invoice->amount_paid;

            $item = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant' => $invoice->lease->tenant?->name,
                'unit' => $invoice->lease->unit?->unit_number,
                'building' => $invoice->lease->unit?->building?->name,
                'balance' => (float) $balance,
                'days_past_due' => $daysPastDue,
                'due_date' => $invoice->due_date->toDateString(),
            ];

            if ($daysPastDue <= 30) {
                $aged['0_30'][] = $item;
            } elseif ($daysPastDue <= 60) {
                $aged['31_60'][] = $item;
            } elseif ($daysPastDue <= 90) {
                $aged['61_90'][] = $item;
            } else {
                $aged['90_plus'][] = $item;
            }
        }

        return response()->json([
            'total_overdue' => (float) $overdueInvoices->sum(fn ($i) => $i->total_due - $i->amount_paid),
            'invoice_count' => $overdueInvoices->count(),
            'aged_receivables' => $aged,
            'summary' => [
                '0_30_days' => (float) collect($aged['0_30'])->sum('balance'),
                '31_60_days' => (float) collect($aged['31_60'])->sum('balance'),
                '61_90_days' => (float) collect($aged['61_90'])->sum('balance'),
                '90_plus_days' => (float) collect($aged['90_plus'])->sum('balance'),
            ],
        ]);
    }

    public function arrearsV2(Request $request)
    {
        $user = $request->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $summary = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->selectRaw("
                COUNT(*) as invoice_count,
                COALESCE(SUM(total_due - amount_paid), 0) as total_overdue,
                COALESCE(SUM(CASE WHEN julianday('now') - julianday(due_date) <= 30 THEN total_due - amount_paid ELSE 0 END), 0) as days_0_30,
                COALESCE(SUM(CASE WHEN julianday('now') - julianday(due_date) > 30 AND julianday('now') - julianday(due_date) <= 60 THEN total_due - amount_paid ELSE 0 END), 0) as days_31_60,
                COALESCE(SUM(CASE WHEN julianday('now') - julianday(due_date) > 60 AND julianday('now') - julianday(due_date) <= 90 THEN total_due - amount_paid ELSE 0 END), 0) as days_61_90,
                COALESCE(SUM(CASE WHEN julianday('now') - julianday(due_date) > 90 THEN total_due - amount_paid ELSE 0 END), 0) as days_90_plus
            ")
            ->first();

        $perPage = min((int) $request->input('per_page', 25), 100);

        $invoices = Invoice::where('landlord_id', $landlordId)
            ->where('status', 'overdue')
            ->with(['lease.tenant:id,name,email,mobile_number', 'lease.unit:id,unit_number,building_id', 'lease.unit.building:id,name'])
            ->orderBy('due_date', 'asc')
            ->cursorPaginate($perPage);

        $now = Carbon::now();

        $transformedInvoices = $invoices->getCollection()->map(function ($invoice) use ($now) {
            $daysPastDue = $invoice->due_date->diffInDays($now);

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'tenant' => $invoice->lease->tenant?->name,
                'tenant_email' => $invoice->lease->tenant?->email,
                'tenant_mobile' => $invoice->lease->tenant?->mobile_number,
                'unit' => $invoice->lease->unit?->unit_number,
                'building' => $invoice->lease->unit?->building?->name,
                'balance' => (float) ($invoice->total_due - $invoice->amount_paid),
                'days_past_due' => $daysPastDue,
                'due_date' => $invoice->due_date->toDateString(),
                'age_bucket' => $daysPastDue <= 30 ? '0_30' : ($daysPastDue <= 60 ? '31_60' : ($daysPastDue <= 90 ? '61_90' : '90_plus')),
            ];
        });

        return response()->json([
            'total_overdue' => (float) $summary->total_overdue,
            'invoice_count' => (int) $summary->invoice_count,
            'summary' => [
                '0_30_days' => (float) $summary->days_0_30,
                '31_60_days' => (float) $summary->days_31_60,
                '61_90_days' => (float) $summary->days_61_90,
                '90_plus_days' => (float) $summary->days_90_plus,
            ],
            'invoices' => $transformedInvoices->values(),
            'pagination' => [
                'next_cursor' => $invoices->nextCursor()?->encode(),
                'prev_cursor' => $invoices->previousCursor()?->encode(),
                'per_page' => $invoices->perPage(),
                'has_more' => $invoices->hasMorePages(),
            ],
        ]);
    }
}
