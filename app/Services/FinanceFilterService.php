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
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
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
            ->withQueryString();
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
        $query = Expense::where('landlord_id', $landlordId)
            ->with(['category', 'vendor', 'property', 'building', 'unit']);

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

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        return $query->orderBy('expense_date', 'desc')
            ->paginate(20)
            ->through(fn ($e) => [
                'id' => $e->id,
                'description' => $e->description,
                'amount' => $e->amount,
                'expense_date' => $e->expense_date->format('Y-m-d'),
                'payment_method' => $e->payment_method,
                'reference' => $e->reference,
                'category' => $e->category?->name,
                'category_color' => $e->category?->color,
                'vendor' => $e->vendor?->name,
                'location' => $e->getLocationLabel(),
                'is_recurring' => $e->is_recurring,
            ])
            ->withQueryString();
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

        return $query->orderBy('due_date', 'asc')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'total_due' => $i->total_due,
                'amount_paid' => $i->amount_paid,
                'balance' => $i->total_due - $i->amount_paid,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'days_overdue' => $i->due_date ? now()->diffInDays($i->due_date, false) * -1 : 0,
                'tenant' => $i->lease?->tenant ? [
                    'id' => $i->lease->tenant->id,
                    'name' => $i->lease->tenant->name,
                    'email' => $i->lease->tenant->email,
                    'phone' => $i->lease->tenant->mobile_number,
                ] : null,
                'unit' => $i->lease?->unit?->unit_number ?? 'N/A',
                'building' => $i->lease?->unit?->building?->name ?? 'N/A',
            ])
            ->toArray();
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
