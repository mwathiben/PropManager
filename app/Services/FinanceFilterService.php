<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Vendor;
use App\Transformers\DepositTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FinanceFilterService
{
    public function getPaginatedInvoices(Request $request, int $landlordId): LengthAwarePaginator
    {
        $query = Invoice::where('landlord_id', $landlordId)
            ->with([
                'lease.tenant:id,name,email',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
                // Phase-98: water-client invoices have no lease — load the connection
                // so the list can show the client + line identifier instead of "N/A".
                'waterConnection:id,user_id,client_name,identifier',
                'waterConnection.client:id,name,email',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('waterConnection', fn ($q) => $q->where('client_name', 'like', "%{$search}%")
                        ->orWhere('identifier', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            // Phase-98: attach a lease-agnostic recipient label so the hub list shows
            // either the tenant + unit or the water client + line identifier.
            ->through(function (Invoice $invoice) {
                $invoice->setAttribute('recipient', $invoice->recipientLabel());

                return $invoice;
            });
    }

    public function getPaginatedPayments(Request $request, int $landlordId): LengthAwarePaginator
    {
        $query = Payment::where('landlord_id', $landlordId)
            ->with([
                'invoice:id,invoice_number,total_due',
                'lease.tenant:id,name,email',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        return $query->orderBy('payment_date', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    public function getPaginatedRefunds(Request $request, int $landlordId): LengthAwarePaginator
    {
        $query = Refund::where('landlord_id', $landlordId)
            ->with([
                'payment:id,amount,payment_method,reference',
                'payment.lease.tenant:id,name',
                'payment.lease.unit:id,unit_number,building_id',
                'payment.lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('payment', fn ($q) => $q->where('reference', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    public function getPaginatedDeposits(Request $request, int $landlordId): LengthAwarePaginator
    {
        return $this->buildDepositsQuery($landlordId)
            ->when($request->filled('search'), fn (Builder $q) => $this->applyDepositSearch($q, $request->search))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('deposit_status', $request->status))
            ->when($request->filled('building_id'), fn (Builder $q) => $q->whereHas('unit', fn ($u) => $u->where('building_id', $request->building_id)))
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($lease) => DepositTransformer::transform($lease))
            ->withQueryString();
    }

    private function buildDepositsQuery(int $landlordId): Builder
    {
        return Lease::where('landlord_id', $landlordId)
            ->where('deposit_amount', '>', 0)
            ->with([
                'tenant:id,name,email',
                'unit:id,unit_number,building_id',
                'unit.building:id,name',
                'depositTransactions' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
                'depositTransactions.processedBy:id,name',
            ]);
    }

    private function applyDepositSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orWhereHas('unit', fn ($q) => $q->where('unit_number', 'like', "%{$search}%"));
        });
    }

    public function getPaginatedExpenses(Request $request, int $landlordId): LengthAwarePaginator
    {
        $query = $this->buildExpensesQuery($landlordId);
        $this->applyExpenseFilters($query, $request);

        return $query->orderBy('expense_date', 'desc')
            ->paginate(20)
            ->through(fn ($e) => $this->transformExpense($e))
            ->withQueryString();
    }

    private function buildExpensesQuery(int $landlordId): Builder
    {
        return Expense::where('landlord_id', $landlordId)
            ->with(['category', 'vendor', 'property', 'building', 'unit']);
    }

    private function applyExpenseFilters(Builder $query, Request $request): void
    {
        $this->applyExpenseLookupFilters($query, $request);
        $this->applyExpenseDateFilters($query, $request);
    }

    private function applyExpenseLookupFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }
    }

    private function applyExpenseDateFilters(Builder $query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
    }

    private function transformExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'expense_date' => $expense->expense_date->format('Y-m-d'),
            'payment_method' => $expense->payment_method,
            'reference' => $expense->reference,
            'category' => $expense->category?->name,
            'category_color' => $expense->category?->color,
            'vendor' => $expense->vendor?->name,
            'location' => $expense->getLocationLabel(),
            'is_recurring' => $expense->is_recurring,
        ];
    }

    public function getArrearsData(Request $request, int $landlordId): array
    {
        $query = Invoice::where('landlord_id', $landlordId)
            ->where('status', InvoiceStatus::Overdue)
            ->with([
                'lease.tenant:id,name,email,mobile_number',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('building_id')) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        return $query->get()
            ->map(function ($i) {
                $daysOverdue = $i->due_date ? (int) (now()->diffInDays($i->due_date, false) * -1) : 0;

                return [
                    'id' => $i->id,
                    'invoice_number' => $i->invoice_number,
                    'total_due' => $i->total_due,
                    'amount_paid' => $i->amount_paid,
                    'balance' => $i->total_due - $i->amount_paid,
                    'due_date' => $i->due_date?->format('Y-m-d'),
                    'days_overdue' => $daysOverdue,
                    // Phase-81 ARREARS-DRILL-1: per-row aging bucket for the drill-down.
                    'aging_bucket' => $this->agingBucket($daysOverdue),
                    'tenant' => $i->lease?->tenant ? [
                        'id' => $i->lease->tenant->id,
                        'name' => $i->lease->tenant->name,
                        'email' => $i->lease->tenant->email,
                        'phone' => $i->lease->tenant->mobile_number,
                    ] : null,
                    'unit' => $i->lease?->unit?->unit_number ?? 'N/A',
                    'building' => $i->lease?->unit?->building?->name ?? 'N/A',
                ];
            })
            // Phase-81 ARREARS-DRILL-2: severity-first (most overdue at the top).
            ->sortByDesc('days_overdue')
            ->values()
            ->toArray();
    }

    /**
     * Phase-81 ARREARS-DRILL-1: aging bucket key for an overdue count.
     */
    private function agingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    public function getUnmatchedPayments(int $landlordId): array
    {
        return Payment::where('landlord_id', $landlordId)
            ->whereNull('invoice_id')
            ->with(['lease.tenant:id,name', 'lease.unit:id,unit_number'])
            ->orderBy('payment_date', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'payment_method' => $p->payment_method,
                'payment_date' => $p->payment_date->format('Y-m-d'),
                'reference' => $p->reference,
                'tenant_name' => $p->lease?->tenant?->name ?? 'Unknown',
                'unit' => $p->lease?->unit?->unit_number ?? 'N/A',
            ])
            ->toArray();
    }

    public function getExpenseCategories(int $landlordId): array
    {
        return ExpenseCategory::where('landlord_id', $landlordId)
            ->withCount('expenses')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'color' => $c->color,
                'is_active' => $c->is_active,
                'expense_count' => $c->expenses_count,
            ])
            ->toArray();
    }

    public function getVendors(int $landlordId): array
    {
        return Vendor::where('landlord_id', $landlordId)
            ->withSum('expenses', 'amount')
            ->with('specialties:id,vendor_id,category')
            ->orderBy('name')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'contact_person' => $v->contact_person,
                'email' => $v->email,
                'phone' => $v->phone,
                // Include the editable fields so the edit form round-trips them
                // instead of saving blanks over the stored values.
                'address' => $v->address,
                'tax_id' => $v->tax_id,
                'notes' => $v->notes,
                'is_active' => $v->is_active,
                'specialties' => $v->specialties->pluck('category')->all(),
                'total_expenses' => (float) ($v->expenses_sum_amount ?? 0),
            ])
            ->toArray();
    }

    public function getInvoiceStatusOptions(): array
    {
        return InvoiceStatus::options();
    }

    public function getPaymentMethodOptions(): array
    {
        return [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'mobile_money', 'label' => 'Mobile Money'],
            ['value' => 'mpesa', 'label' => 'M-Pesa'],
            ['value' => 'paystack', 'label' => 'Paystack'],
            ['value' => 'stripe', 'label' => 'Card'],
        ];
    }

    public function getRefundStatusOptions(): array
    {
        return [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'failed', 'label' => 'Failed'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }
}
