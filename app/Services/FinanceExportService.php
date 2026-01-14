<?php

namespace App\Services;

use App\Exports\DepositsExport;
use App\Exports\ExpensesExport;
use App\Exports\FinanceReportExport;
use App\Exports\InvoicesExport;
use App\Exports\PaymentsExport;
use App\Exports\Streaming\StreamingInvoicesExport;
use App\Exports\Streaming\StreamingPaymentsExport;
use App\Exports\VendorExpenseExport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Vendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceExportService
{
    private const STREAM_THRESHOLD = 10000;

    public function __construct(
        private readonly FinanceStatsService $statsService,
        private readonly FinanceReportService $reportService
    ) {}

    public function exportInvoices(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildInvoiceQuery($filters);
        $filename = 'invoices_'.now()->format('Y_m_d_His');

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingInvoicesExport(clone $query), $filename.'.xlsx');
        }

        $invoices = $query->orderBy('created_at', 'desc')->get();

        return match ($format) {
            'pdf' => $this->invoicesToPdf($invoices, $filters, $filename),
            'csv' => $this->toCsv($this->formatInvoicesForCsv($invoices), $this->getInvoiceHeadings(), $filename.'.csv'),
            default => Excel::download(new InvoicesExport($invoices), $filename.'.xlsx'),
        };
    }

    public function exportPayments(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildPaymentQuery($filters);
        $filename = 'payments_'.now()->format('Y_m_d_His');

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingPaymentsExport(clone $query), $filename.'.xlsx');
        }

        $payments = $query->orderBy('payment_date', 'desc')->get();

        $dateRange = [
            'start' => isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : now()->subMonth(),
            'end' => isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : now(),
        ];

        return match ($format) {
            'pdf' => $this->paymentsToPdf($payments, $filters, $filename),
            'csv' => $this->toCsv($this->formatPaymentsForCsv($payments), $this->getPaymentHeadings(), $filename.'.csv'),
            default => Excel::download(new PaymentsExport($payments, $dateRange), $filename.'.xlsx'),
        };
    }

    public function exportDeposits(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildDepositQuery($filters);
        $deposits = $query->orderBy('created_at', 'desc')->get();
        $filename = 'deposits_report_'.now()->format('Y-m-d');

        $stats = $this->calculateDepositStats($deposits);

        return match ($format) {
            'pdf' => $this->depositsToPdf($deposits, $stats, $filters, $filename),
            'csv' => $this->toCsv($this->formatDepositsForCsv($deposits), $this->getDepositHeadings(), $filename.'.csv'),
            default => Excel::download(new DepositsExport($deposits), $filename.'.xlsx'),
        };
    }

    public function exportExpenses(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildExpenseQuery($filters);
        $expenses = $query->orderBy('expense_date', 'desc')->get();
        $filename = 'expenses_'.now()->format('Y_m_d_His');

        $dateRange = [
            'start' => isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : now()->subMonth(),
            'end' => isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : now(),
        ];

        return match ($format) {
            'pdf' => $this->expensesToPdf($expenses, $filters, $filename),
            'csv' => $this->toCsv($this->formatExpensesForCsv($expenses), $this->getExpenseHeadings(), $filename.'.csv'),
            default => Excel::download(new ExpensesExport($expenses, $dateRange), $filename.'.xlsx'),
        };
    }

    public function exportVendors(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse
    {
        $landlordId = $filters['landlord_id'];

        $vendors = Vendor::where('landlord_id', $landlordId)
            ->withSum('expenses', 'amount')
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        $filename = 'vendors_'.now()->format('Y_m_d_His');

        $dateRange = [
            'start' => now()->subYear(),
            'end' => now(),
        ];

        if ($format === 'csv') {
            return $this->toCsv($this->formatVendorsForCsv($vendors), $this->getVendorHeadings(), $filename.'.csv');
        }

        return Excel::download(new VendorExpenseExport($vendors, $dateRange), $filename.'.xlsx');
    }

    public function exportReports(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $landlordId = $filters['landlord_id'];
        $period = $filters['period'] ?? 12;

        $data = [
            'revenue' => $this->reportService->getRevenueReport($landlordId, $period),
            'collection_rate' => $this->reportService->getCollectionRateReport($landlordId, $period),
            'occupancy' => $this->reportService->getOccupancyReport($landlordId),
            'arrears_aging' => $this->reportService->getArrearsAgingReport($landlordId),
            'expenses_by_category' => $this->reportService->getExpensesByCategoryReport($landlordId, $period),
            'water_consumption' => $this->reportService->getWaterConsumptionReport($landlordId, $period),
            'top_performing_units' => $this->reportService->getTopPerformingUnitsReport($landlordId, $period),
        ];

        $filename = 'financial_report_'.now()->format('Y-m-d');

        return match ($format) {
            'pdf' => $this->reportsToPdf($data, $period, $filename),
            'csv' => $this->reportsToCsv($data, $filename),
            default => Excel::download(new FinanceReportExport($data, $period), $filename.'.xlsx'),
        };
    }

    protected function buildInvoiceQuery(array $filters): Builder
    {
        $query = Invoice::where('landlord_id', $filters['landlord_id'])
            ->with([
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['building_id'])) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $filters['building_id']));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function buildPaymentQuery(array $filters): Builder
    {
        $query = Payment::where('landlord_id', $filters['landlord_id'])
            ->where('is_voided', false)
            ->with([
                'invoice:id,invoice_number',
                'lease.tenant:id,name',
                'lease.unit:id,unit_number,building_id',
                'lease.unit.building:id,name',
            ]);

        if (! empty($filters['method'])) {
            $query->where('payment_method', $filters['method']);
        }

        if (! empty($filters['building_id'])) {
            $query->whereHas('lease.unit', fn ($q) => $q->where('building_id', $filters['building_id']));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function buildDepositQuery(array $filters): Builder
    {
        $query = Lease::where('landlord_id', $filters['landlord_id'])
            ->where('deposit_amount', '>', 0)
            ->with([
                'tenant:id,name,email',
                'unit:id,unit_number,building_id',
                'unit.building:id,name',
            ]);

        if (! empty($filters['status'])) {
            $query->where('deposit_status', $filters['status']);
        }

        if (! empty($filters['building_id'])) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $filters['building_id']));
        }

        return $query;
    }

    protected function buildExpenseQuery(array $filters): Builder
    {
        $query = Expense::where('landlord_id', $filters['landlord_id'])
            ->with(['category', 'vendor', 'property', 'building', 'unit']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (! empty($filters['building_id'])) {
            $query->where('building_id', $filters['building_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('expense_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('expense_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function toCsv(Collection $data, array $headings, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);
            foreach ($data as $row) {
                fputcsv($handle, array_values((array) $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    protected function invoicesToPdf(Collection $invoices, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculateInvoiceSummary($invoices);

        $pdf = Pdf::loadView('exports.invoices', [
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => array_filter([
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ]),
            'landlord' => auth()->user(),
            'generated_at' => now()->format('F j, Y g:i A'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function paymentsToPdf(Collection $payments, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculatePaymentSummary($payments);
        $methodBreakdown = $this->statsService->calculateMethodBreakdown($payments);

        $pdf = Pdf::loadView('exports.payments', [
            'payments' => $payments,
            'summary' => $summary,
            'method_breakdown' => $methodBreakdown,
            'filters' => array_filter([
                'method' => $filters['method'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ]),
            'landlord' => auth()->user(),
            'generated_at' => now()->format('F j, Y g:i A'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function depositsToPdf(Collection $deposits, array $stats, array $filters, string $filename): Response
    {
        $pdf = Pdf::loadView('exports.deposits', [
            'deposits' => $deposits,
            'stats' => $stats,
            'landlord' => auth()->user(),
            'generated_at' => now()->format('M j, Y g:i A'),
            'filters' => array_filter([
                'status' => $filters['status'] ?? null,
                'building_id' => $filters['building_id'] ?? null,
            ]),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function expensesToPdf(Collection $expenses, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculateExpenseSummary($expenses);
        $categoryBreakdown = $this->statsService->calculateExpenseCategoryBreakdown($expenses);

        $pdf = Pdf::loadView('exports.expenses', [
            'expenses' => $expenses,
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'filters' => array_filter([
                'category_id' => $filters['category_id'] ?? null,
                'vendor_id' => $filters['vendor_id'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ]),
            'landlord' => auth()->user(),
            'generated_at' => now()->format('F j, Y g:i A'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function reportsToPdf(array $data, int $period, string $filename): Response
    {
        $pdf = Pdf::loadView('exports.financial-report', [
            'data' => $data,
            'period' => $period,
            'landlord' => auth()->user(),
            'generated_at' => now()->format('M j, Y g:i A'),
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function reportsToCsv(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Financial Report - '.now()->format('F j, Y')]);
            fputcsv($handle, []);

            fputcsv($handle, ['=== Revenue Summary ===']);
            fputcsv($handle, ['Month', 'Revenue', 'Collected', 'Outstanding']);
            foreach ($data['revenue'] as $row) {
                fputcsv($handle, [$row['month'], $row['revenue'], $row['collected'], $row['outstanding']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['=== Occupancy ===']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Units', $data['occupancy']['total_units'] ?? 0]);
            fputcsv($handle, ['Occupied', $data['occupancy']['occupied'] ?? 0]);
            fputcsv($handle, ['Vacant', $data['occupancy']['vacant'] ?? 0]);
            fputcsv($handle, ['Occupancy Rate', ($data['occupancy']['occupancy_rate'] ?? 0).'%']);
            fputcsv($handle, []);

            fputcsv($handle, ['=== Arrears Aging ===']);
            fputcsv($handle, ['Aging Bracket', 'Count', 'Amount']);
            foreach ($data['arrears_aging'] as $row) {
                fputcsv($handle, [$row['bracket'], $row['count'], $row['amount']]);
            }

            fclose($handle);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    protected function calculateDepositStats(Collection $deposits): array
    {
        return [
            'total_held' => $deposits->where('deposit_status', 'held')->sum('deposit_amount'),
            'total_refunded' => $deposits->whereIn('deposit_status', ['refunded', 'partial_refund'])->sum('deposit_refund_amount'),
            'total_forfeited' => $deposits->where('deposit_status', 'forfeited')->sum('deposit_amount'),
            'count_held' => $deposits->where('deposit_status', 'held')->count(),
            'count_refunded' => $deposits->whereIn('deposit_status', ['refunded', 'partial_refund'])->count(),
            'count_forfeited' => $deposits->where('deposit_status', 'forfeited')->count(),
        ];
    }

    protected function formatInvoicesForCsv(Collection $invoices): Collection
    {
        return $invoices->map(fn ($inv) => [
            'invoice_number' => $inv->invoice_number,
            'date' => $inv->created_at?->format('Y-m-d'),
            'due_date' => $inv->due_date?->format('Y-m-d'),
            'tenant' => $inv->lease->tenant->name ?? 'N/A',
            'unit' => $inv->lease->unit->unit_number ?? 'N/A',
            'building' => $inv->lease->unit->building->name ?? 'N/A',
            'rent' => $inv->rent_amount,
            'water' => $inv->water_charges,
            'arrears' => $inv->arrears_amount,
            'total_due' => $inv->total_due,
            'amount_paid' => $inv->amount_paid,
            'balance' => $inv->total_due - $inv->amount_paid,
            'status' => ucfirst($inv->status),
        ]);
    }

    protected function formatPaymentsForCsv(Collection $payments): Collection
    {
        return $payments->map(fn ($p) => [
            'date' => $p->payment_date?->format('Y-m-d'),
            'reference' => $p->reference ?? '',
            'tenant' => $p->lease->tenant->name ?? 'N/A',
            'unit' => $p->lease->unit->unit_number ?? 'N/A',
            'building' => $p->lease->unit->building->name ?? 'N/A',
            'amount' => $p->amount,
            'method' => ucfirst(str_replace('_', ' ', $p->payment_method)),
            'invoice' => $p->invoice->invoice_number ?? 'Unallocated',
            'status' => $p->status ?? 'completed',
        ]);
    }

    protected function formatDepositsForCsv(Collection $deposits): Collection
    {
        return $deposits->map(fn ($d) => [
            'tenant' => $d->tenant->name ?? 'N/A',
            'unit' => $d->unit->unit_number ?? 'N/A',
            'building' => $d->unit->building->name ?? 'N/A',
            'deposit_amount' => $d->deposit_amount,
            'status' => ucfirst(str_replace('_', ' ', $d->deposit_status ?? 'held')),
            'refund_amount' => $d->deposit_refund_amount ?? 0,
            'deductions' => $d->deposit_deductions ?? 0,
            'lease_start' => $d->start_date?->format('Y-m-d'),
            'lease_end' => $d->end_date?->format('Y-m-d'),
        ]);
    }

    protected function formatExpensesForCsv(Collection $expenses): Collection
    {
        return $expenses->map(fn ($e) => [
            'date' => $e->expense_date?->format('Y-m-d'),
            'description' => $e->description,
            'category' => $e->category->name ?? 'Uncategorized',
            'vendor' => $e->vendor->name ?? '',
            'property' => $e->property->name ?? '',
            'building' => $e->building->name ?? '',
            'amount' => $e->amount,
            'payment_method' => ucfirst(str_replace('_', ' ', $e->payment_method ?? '')),
            'reference' => $e->reference ?? '',
            'is_recurring' => $e->is_recurring ? 'Yes' : 'No',
        ]);
    }

    protected function formatVendorsForCsv(Collection $vendors): Collection
    {
        return $vendors->map(fn ($v) => [
            'name' => $v->name,
            'contact_person' => $v->contact_person ?? '',
            'email' => $v->email ?? '',
            'phone' => $v->phone ?? '',
            'total_expenses' => $v->expenses_sum_amount ?? 0,
            'expense_count' => $v->expenses_count ?? 0,
            'status' => $v->is_active ? 'Active' : 'Inactive',
        ]);
    }

    protected function getInvoiceHeadings(): array
    {
        return [
            'Invoice Number', 'Date', 'Due Date', 'Tenant', 'Unit', 'Building',
            'Rent (KES)', 'Water (KES)', 'Arrears (KES)', 'Total Due (KES)',
            'Amount Paid (KES)', 'Balance (KES)', 'Status',
        ];
    }

    protected function getPaymentHeadings(): array
    {
        return [
            'Date', 'Reference', 'Tenant', 'Unit', 'Building',
            'Amount (KES)', 'Method', 'Invoice', 'Status',
        ];
    }

    protected function getDepositHeadings(): array
    {
        return [
            'Tenant', 'Unit', 'Building', 'Deposit Amount (KES)', 'Status',
            'Refund Amount (KES)', 'Deductions (KES)', 'Lease Start', 'Lease End',
        ];
    }

    protected function getExpenseHeadings(): array
    {
        return [
            'Date', 'Description', 'Category', 'Vendor', 'Property', 'Building',
            'Amount (KES)', 'Payment Method', 'Reference', 'Recurring',
        ];
    }

    protected function getVendorHeadings(): array
    {
        return [
            'Name', 'Contact Person', 'Email', 'Phone',
            'Total Expenses (KES)', 'Expense Count', 'Status',
        ];
    }

    public function shouldStream(Builder $query): bool
    {
        return $query->count() > self::STREAM_THRESHOLD;
    }
}
