<?php

namespace App\Services;

use App\Enums\Currency;
use App\Exports\DepositsExport;
use App\Exports\ExpensesExport;
use App\Exports\FinanceReportExport;
use App\Exports\InvoicesExport;
use App\Exports\PaymentsExport;
use App\Exports\Streaming\StreamingDepositsExport;
use App\Exports\Streaming\StreamingExpensesExport;
use App\Exports\Streaming\StreamingInvoicesExport;
use App\Exports\Streaming\StreamingPaymentsExport;
use App\Exports\VendorExpenseExport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Vendor;
use App\Support\DateFilter;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $currency = $this->getLandlordCurrency();

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingInvoicesExport(clone $query, $currency->value), $filename.'.xlsx');
        }

        if ($format === 'csv') {
            return $this->streamInvoicesToCsv(clone $query, $filename.'.csv', $currency->value);
        }

        $invoices = $query->orderBy('created_at', 'desc')->lazy(1000)->collect();

        return match ($format) {
            'pdf' => $this->invoicesToPdf($invoices, $filters, $filename),
            default => Excel::download(new InvoicesExport($invoices, currencyCode: $currency->value), $filename.'.xlsx'),
        };
    }

    public function exportPayments(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildPaymentQuery($filters);
        $filename = 'payments_'.now()->format('Y_m_d_His');
        $currency = $this->getLandlordCurrency();

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingPaymentsExport(clone $query, $currency->value), $filename.'.xlsx');
        }

        if ($format === 'csv') {
            return $this->streamPaymentsToCsv(clone $query, $filename.'.csv', $currency->value);
        }

        $payments = $query->orderBy('payment_date', 'desc')->lazy(1000)->collect();

        // Phase-17 TIME-2: parse date filters in the authed user's
        // timezone so a non-Kenya user filtering 'their day' actually
        // gets their day's boundaries, not Africa/Nairobi-midnight.
        $user = auth()->user();
        $dateRange = [
            'start' => DateFilter::parseUserDayOr($filters['date_from'] ?? null, $user, now()->subMonth(), 'startOfDay'),
            'end' => DateFilter::parseUserDayOr($filters['date_to'] ?? null, $user, now(), 'endOfDay'),
        ];

        return match ($format) {
            'pdf' => $this->paymentsToPdf($payments, $filters, $filename),
            default => Excel::download(new PaymentsExport($payments, $dateRange, $currency->value), $filename.'.xlsx'),
        };
    }

    public function exportDeposits(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildDepositQuery($filters);
        $filename = 'deposits_report_'.now()->format('Y-m-d');
        $currency = $this->getLandlordCurrency();

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingDepositsExport(clone $query, $currency->value), $filename.'.xlsx');
        }

        if ($format === 'csv') {
            return $this->streamDepositsToCsv(clone $query, $filename.'.csv', $currency->value);
        }

        $deposits = $query->orderBy('created_at', 'desc')->lazy(1000)->collect();
        $stats = $this->calculateDepositStats($deposits);

        return match ($format) {
            'pdf' => $this->depositsToPdf($deposits, $stats, $filters, $filename),
            default => Excel::download(new DepositsExport($deposits, $currency->value), $filename.'.xlsx'),
        };
    }

    public function exportExpenses(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $query = $this->buildExpenseQuery($filters);
        $filename = 'expenses_'.now()->format('Y_m_d_His');
        $currency = $this->getLandlordCurrency();

        if ($format === 'xlsx' && $this->shouldStream($query)) {
            return Excel::download(new StreamingExpensesExport(clone $query, $currency->value), $filename.'.xlsx');
        }

        if ($format === 'csv') {
            return $this->streamExpensesToCsv(clone $query, $filename.'.csv', $currency->value);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->lazy(1000)->collect();

        // Phase-17 TIME-2: user-TZ date filtering.
        $user = auth()->user();
        $dateRange = [
            'start' => DateFilter::parseUserDayOr($filters['date_from'] ?? null, $user, now()->subMonth(), 'startOfDay'),
            'end' => DateFilter::parseUserDayOr($filters['date_to'] ?? null, $user, now(), 'endOfDay'),
        ];

        return match ($format) {
            'pdf' => $this->expensesToPdf($expenses, $filters, $filename),
            default => Excel::download(new ExpensesExport($expenses, $dateRange, $currency->value), $filename.'.xlsx'),
        };
    }

    public function exportVendors(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse
    {
        $landlordId = $filters['landlord_id'];
        $filename = 'vendors_'.now()->format('Y_m_d_His');
        $currency = $this->getLandlordCurrency();

        $query = Vendor::where('landlord_id', $landlordId)
            ->withSum('expenses', 'amount')
            ->withCount('expenses')
            ->orderBy('name');

        if ($format === 'csv') {
            return $this->streamVendorsToCsv(clone $query, $filename.'.csv', $currency->value);
        }

        $vendors = $query->lazy(1000)->collect();

        $dateRange = [
            'start' => now()->subYear(),
            'end' => now(),
        ];

        return Excel::download(new VendorExpenseExport($vendors, $dateRange, $currency->value), $filename.'.xlsx');
    }

    public function exportReports(array $filters, string $format = 'xlsx'): BinaryFileResponse|StreamedResponse|Response
    {
        $landlordId = $filters['landlord_id'];
        $period = $filters['period'] ?? 12;
        $currency = $this->getLandlordCurrency();

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
            default => Excel::download(new FinanceReportExport($data, $period, $currency->value), $filename.'.xlsx'),
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
        $query = Payment::withArchived()->where('landlord_id', $filters['landlord_id'])
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
        // PERF-R3: column-restrict the category eager-load — the export
        // breakdown only reads name + color (plus id for the join).
        $query = Expense::where('landlord_id', $filters['landlord_id'])
            ->with(['category:id,name,color', 'vendor', 'property', 'building', 'unit']);

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

    protected function streamInvoicesToCsv(Builder $query, string $filename, string $currencyCode = 'KES'): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->getInvoiceHeadings($currencyCode));

            foreach ($query->orderBy('created_at', 'desc')->cursor() as $inv) {
                fputcsv($handle, [
                    $inv->invoice_number,
                    $inv->created_at?->format('Y-m-d'),
                    $inv->due_date?->format('Y-m-d'),
                    $inv->lease->tenant->name ?? 'N/A',
                    $inv->lease->unit->unit_number ?? 'N/A',
                    $inv->lease->unit->building->name ?? 'N/A',
                    $inv->rent_amount,
                    $inv->water_charges,
                    $inv->arrears_amount,
                    $inv->total_due,
                    $inv->amount_paid,
                    $inv->total_due - $inv->amount_paid,
                    ucfirst($inv->status),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    protected function streamPaymentsToCsv(Builder $query, string $filename, string $currencyCode = 'KES'): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->getPaymentHeadings($currencyCode));

            foreach ($query->orderBy('payment_date', 'desc')->cursor() as $p) {
                fputcsv($handle, [
                    $p->payment_date?->format('Y-m-d'),
                    $p->reference ?? '',
                    $p->lease->tenant->name ?? 'N/A',
                    $p->lease->unit->unit_number ?? 'N/A',
                    $p->lease->unit->building->name ?? 'N/A',
                    $p->amount,
                    ucfirst(str_replace('_', ' ', $p->payment_method)),
                    $p->invoice->invoice_number ?? 'Unallocated',
                    $p->status ?? 'completed',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    protected function streamDepositsToCsv(Builder $query, string $filename, string $currencyCode = 'KES'): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->getDepositHeadings($currencyCode));

            foreach ($query->orderBy('created_at', 'desc')->cursor() as $d) {
                fputcsv($handle, [
                    $d->tenant->name ?? 'N/A',
                    $d->unit->unit_number ?? 'N/A',
                    $d->unit->building->name ?? 'N/A',
                    $d->deposit_amount,
                    ucfirst(str_replace('_', ' ', $d->deposit_status ?? 'held')),
                    $d->deposit_refund_amount ?? 0,
                    $d->deposit_deductions ?? 0,
                    $d->start_date?->format('Y-m-d'),
                    $d->end_date?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    protected function streamExpensesToCsv(Builder $query, string $filename, string $currencyCode = 'KES'): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->getExpenseHeadings($currencyCode));

            foreach ($query->orderBy('expense_date', 'desc')->cursor() as $e) {
                fputcsv($handle, [
                    $e->expense_date?->format('Y-m-d'),
                    $e->description,
                    $e->category->name ?? 'Uncategorized',
                    $e->vendor->name ?? '',
                    $e->property->name ?? '',
                    $e->building->name ?? '',
                    $e->amount,
                    ucfirst(str_replace('_', ' ', $e->payment_method ?? '')),
                    $e->reference ?? '',
                    $e->is_recurring ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    protected function streamVendorsToCsv(Builder $query, string $filename, string $currencyCode = 'KES'): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $currencyCode) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->getVendorHeadings($currencyCode));

            foreach ($query->cursor() as $v) {
                fputcsv($handle, [
                    $v->name,
                    $v->contact_person ?? '',
                    $v->email ?? '',
                    $v->phone ?? '',
                    $v->expenses_sum_amount ?? 0,
                    $v->expenses_count ?? 0,
                    $v->is_active ? 'Active' : 'Inactive',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    protected function invoicesToPdf(Collection $invoices, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculateInvoiceSummary($invoices);
        $currency = $this->getLandlordCurrency();

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
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function paymentsToPdf(Collection $payments, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculatePaymentSummary($payments);
        $methodBreakdown = $this->statsService->calculateMethodBreakdown($payments);
        $currency = $this->getLandlordCurrency();

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
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function depositsToPdf(Collection $deposits, array $stats, array $filters, string $filename): Response
    {
        $currency = $this->getLandlordCurrency();

        $pdf = Pdf::loadView('exports.deposits', [
            'deposits' => $deposits,
            'stats' => $stats,
            'landlord' => auth()->user(),
            'generated_at' => now()->format('M j, Y g:i A'),
            'filters' => array_filter([
                'status' => $filters['status'] ?? null,
                'building_id' => $filters['building_id'] ?? null,
            ]),
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function expensesToPdf(Collection $expenses, array $filters, string $filename): Response
    {
        $summary = $this->statsService->calculateExpenseSummary($expenses);
        $categoryBreakdown = $this->statsService->calculateExpenseCategoryBreakdown($expenses);
        $currency = $this->getLandlordCurrency();

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
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        return $pdf->download($filename.'.pdf');
    }

    protected function reportsToPdf(array $data, int $period, string $filename): Response
    {
        $currency = $this->getLandlordCurrency();

        $pdf = Pdf::loadView('exports.financial-report', [
            'data' => $data,
            'period' => $period,
            'landlord' => auth()->user(),
            'generated_at' => now()->format('M j, Y g:i A'),
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
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

    protected function getInvoiceHeadings(string $currencyCode = 'KES'): array
    {
        return [
            'Invoice Number', 'Date', 'Due Date', 'Tenant', 'Unit', 'Building',
            "Rent ({$currencyCode})", "Water ({$currencyCode})", "Arrears ({$currencyCode})", "Total Due ({$currencyCode})",
            "Amount Paid ({$currencyCode})", "Balance ({$currencyCode})", 'Status',
        ];
    }

    protected function getPaymentHeadings(string $currencyCode = 'KES'): array
    {
        return [
            'Date', 'Reference', 'Tenant', 'Unit', 'Building',
            "Amount ({$currencyCode})", 'Method', 'Invoice', 'Status',
        ];
    }

    protected function getDepositHeadings(string $currencyCode = 'KES'): array
    {
        return [
            'Tenant', 'Unit', 'Building', "Deposit Amount ({$currencyCode})", 'Status',
            "Refund Amount ({$currencyCode})", "Deductions ({$currencyCode})", 'Lease Start', 'Lease End',
        ];
    }

    protected function getExpenseHeadings(string $currencyCode = 'KES'): array
    {
        return [
            'Date', 'Description', 'Category', 'Vendor', 'Property', 'Building',
            "Amount ({$currencyCode})", 'Payment Method', 'Reference', 'Recurring',
        ];
    }

    protected function getVendorHeadings(string $currencyCode = 'KES'): array
    {
        return [
            'Name', 'Contact Person', 'Email', 'Phone',
            "Total Expenses ({$currencyCode})", 'Expense Count', 'Status',
        ];
    }

    public function shouldStream(Builder $query): bool
    {
        return $query->count() > self::STREAM_THRESHOLD;
    }

    protected function getLandlordCurrency(): Currency
    {
        $user = auth()->user();

        if (! $user) {
            return Currency::default();
        }

        $landlordId = $user->role === 'landlord' ? $user->id : $user->landlord_id;
        $config = PaymentConfiguration::where('landlord_id', $landlordId)->first();

        return $config?->default_currency ?? Currency::default();
    }
}
