<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Exports\ArrearsReportExport;
use App\Exports\FinancialReportExport;
use App\Exports\OccupancyReportExport;
use App\Exports\PaymentsExport;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Services\ReportService;
use App\Support\DateFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display reports dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();
        $period = $request->query('period', 'month');

        $analytics = $this->reportService->getDashboardAnalytics($landlordId, $period);

        return Inertia::render('Reports/Index', [
            'analytics' => $analytics,
            'availablePeriods' => [
                'week' => 'Last 7 Days',
                'month' => 'Last Month',
                'quarter' => 'Last Quarter',
                'year' => 'Last Year',
            ],
        ]);
    }

    /**
     * Export financial report as PDF
     */
    public function exportPdf(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:financial,occupancy,arrears,water',
            'period' => 'nullable|string|in:week,month,quarter,year',
        ]);

        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();
        $period = $validated['period'] ?? 'month';

        $data = $this->reportService->exportData($landlordId, $validated['report_type'], $period);
        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency ?? Currency::default();

        $pdf = Pdf::loadView('reports.'.$validated['report_type'], [
            'data' => $data,
            'landlord' => $user->isScopeOwner() ? $user : $user->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ]);

        $filename = $validated['report_type'].'_report_'.now()->format('Y_m_d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export report as Excel/CSV
     */
    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|string|in:financial,occupancy,arrears,water,payments',
            'period' => 'nullable|string|in:week,month,quarter,year',
            'format' => 'nullable|string|in:xlsx,csv',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();
        $period = $validated['period'] ?? 'month';
        $format = $validated['format'] ?? 'xlsx';

        $dateRange = $this->getDateRange($request, $period);
        $filename = $validated['report_type'].'_report_'.$dateRange['start']->format('Y_m_d').'.'.$format;
        $currency = PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency ?? Currency::default();

        if ($format === 'xlsx') {
            $export = $this->getExportClass($validated['report_type'], $landlordId, $dateRange, $currency->value);

            if ($export) {
                return Excel::download($export, $filename);
            }
        }

        $data = $this->reportService->exportData($landlordId, $validated['report_type'], $period);
        $csvData = $this->convertToCSV($data, $validated['report_type'], $currency->symbol());

        return Response::make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get date range from request or period
     */
    private function getDateRange(Request $request, string $period): array
    {
        // Phase-17 TIME-2: parse user-supplied date filters in the
        // authed user's timezone (not Africa/Nairobi by default). A
        // user in America/New_York filtering '2026-01-15' now gets
        // their day's boundaries, not Nairobi-midnight (off-by-day).
        $user = $request->user();

        if ($request->filled('date_from') && $request->filled('date_to')) {
            return [
                'start' => DateFilter::parseUserDay($request->date_from, $user, 'startOfDay'),
                'end' => DateFilter::parseUserDay($request->date_to, $user, 'endOfDay'),
            ];
        }

        $dateRange = $this->reportService->getDateRange($period);

        // getDateRange returns app-TZ-relative period boundaries — these
        // are operator-defined periods (this_month, last_month) so they
        // stay anchored to the application timezone by design.
        return [
            'start' => Carbon::parse($dateRange['start']),
            'end' => Carbon::parse($dateRange['end']),
        ];
    }

    /**
     * Get export class based on report type
     */
    private function getExportClass(string $reportType, int $landlordId, array $dateRange, string $currencyCode = 'KES')
    {
        return match ($reportType) {
            'financial' => new FinancialReportExport($landlordId, $dateRange, $currencyCode),
            'occupancy' => new OccupancyReportExport($landlordId, $currencyCode),
            'arrears' => new ArrearsReportExport($landlordId, $currencyCode),
            'payments' => new PaymentsExport(
                Payment::where('landlord_id', $landlordId)
                    ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
                    ->with(['lease.tenant', 'lease.unit.building', 'invoice'])
                    ->get(),
                $dateRange,
                $currencyCode
            ),
            default => null,
        };
    }

    /**
     * Convert report data to CSV format
     */
    private function convertToCSV(array $data, string $reportType, string $currencySymbol = 'KSh'): string
    {
        $output = fopen('php://temp', 'r+');

        // Add header
        fputcsv($output, [$data['title']]);
        fputcsv($output, ['Generated: '.now()->format('F j, Y g:i A')]);
        fputcsv($output, []);

        // Add data based on report type
        if ($reportType === 'financial') {
            fputcsv($output, ['Financial Summary']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Expected Rent', $currencySymbol.' '.number_format($data['summary']['expected_rent'], 2)]);
            fputcsv($output, ['Collected Rent', $currencySymbol.' '.number_format($data['summary']['collected_rent'], 2)]);
            fputcsv($output, ['Water Charges', $currencySymbol.' '.number_format($data['summary']['water_charges'], 2)]);
            fputcsv($output, ['Outstanding', $currencySymbol.' '.number_format($data['summary']['outstanding'], 2)]);
            fputcsv($output, ['Collection Rate', $data['summary']['collection_percentage'].'%']);
            fputcsv($output, []);

            fputcsv($output, ['Revenue Breakdown']);
            fputcsv($output, ['Category', 'Amount']);
            foreach ($data['summary']['revenue_breakdown'] as $category => $amount) {
                fputcsv($output, [ucfirst($category), $currencySymbol.' '.number_format($amount, 2)]);
            }
        } elseif ($reportType === 'occupancy') {
            fputcsv($output, ['Occupancy Summary']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Units', $data['summary']['total_units']]);
            fputcsv($output, ['Occupied', $data['summary']['occupied']]);
            fputcsv($output, ['Vacant', $data['summary']['vacant']]);
            fputcsv($output, ['Maintenance', $data['summary']['maintenance']]);
            fputcsv($output, ['Arrears', $data['summary']['arrears']]);
            fputcsv($output, ['Occupancy Rate', $data['summary']['occupancy_rate'].'%']);
            fputcsv($output, []);

            fputcsv($output, ['Top Performing Units']);
            fputcsv($output, ['Unit', 'Tenant', 'Collection Rate', 'On-Time Payments']);
            foreach ($data['top_performers'] as $unit) {
                fputcsv($output, [
                    $unit['unit'],
                    $unit['tenant'],
                    $unit['collection_rate'].'%',
                    $unit['on_time_payments'].'/'.$unit['total_invoices'],
                ]);
            }
        } elseif ($reportType === 'arrears') {
            fputcsv($output, ['Arrears Summary']);
            fputcsv($output, ['Total Arrears', $currencySymbol.' '.number_format($data['summary']['total_arrears'], 2)]);
            fputcsv($output, ['Number of Overdue Invoices', $data['summary']['count']]);
            fputcsv($output, []);

            fputcsv($output, ['Aging Analysis']);
            fputcsv($output, ['Period', 'Amount']);
            foreach ($data['aging_breakdown'] as $period => $amount) {
                fputcsv($output, [$period.' days', $currencySymbol.' '.number_format($amount, 2)]);
            }
            fputcsv($output, []);

            fputcsv($output, ['Arrears Details']);
            fputcsv($output, ['Unit', 'Tenant', 'Amount', 'Days Overdue', 'Invoice Number']);
            foreach ($data['details'] as $detail) {
                fputcsv($output, [
                    $detail['unit'],
                    $detail['tenant'],
                    $currencySymbol.' '.number_format($detail['amount'], 2),
                    $detail['days_overdue'],
                    $detail['invoice_number'],
                ]);
            }
        } elseif ($reportType === 'water') {
            fputcsv($output, ['Water Consumption Summary']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Consumption', $data['summary']['total_consumption'].' units']);
            fputcsv($output, ['Total Cost', $currencySymbol.' '.number_format($data['summary']['total_cost'], 2)]);
            fputcsv($output, ['Average Consumption', $data['summary']['average_consumption'].' units']);
            fputcsv($output, ['Readings Count', $data['summary']['readings_count']]);
            fputcsv($output, []);

            fputcsv($output, ['Top Consumers']);
            fputcsv($output, ['Unit', 'Consumption', 'Cost']);
            foreach ($data['top_consumers'] as $consumer) {
                fputcsv($output, [
                    $consumer['unit'],
                    $consumer['consumption'].' units',
                    $currencySymbol.' '.number_format($consumer['cost'], 2),
                ]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Get real-time metrics (for AJAX updates)
     */
    public function getMetrics(Request $request)
    {
        $user = auth()->user();
        $landlordId = $user->effectiveScopeId();
        $period = $request->query('period', 'month');

        $analytics = $this->reportService->getDashboardAnalytics($landlordId, $period);

        return response()->json($analytics);
    }
}
