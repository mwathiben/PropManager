<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\WithFinanceRendering;
use App\Http\Traits\WithLandlordScope;
use App\Services\FinanceExportService;
use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinanceReportController extends Controller
{
    use WithFinanceRendering;
    use WithLandlordScope;

    public function __construct(
        protected FinanceReportService $reportService,
        protected FinanceExportService $exportService,
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $user = auth()->user();

        $period = $request->query('period', '12');
        $buildingId = $request->query('building_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $compare = $request->boolean('compare', false);

        $dateRange = $this->reportService->getReportDateRange($period, $dateFrom, $dateTo, $landlordId);

        $hasWaterAccess = $user->isScopeOwner()
            ? $user->canAccessFeature('water_billing')
            : $user->landlord?->canAccessFeature('water_billing') ?? false;

        $currentData = [
            'revenueData' => $this->reportService->getRevenueReportFiltered($landlordId, $dateRange, $buildingId),
            'collectionRate' => $this->reportService->getCollectionRateReportFiltered($landlordId, $dateRange, $buildingId),
            'occupancyData' => $this->reportService->getOccupancyReport($landlordId, $buildingId),
            'arrearsAging' => $this->reportService->getArrearsAgingReport($landlordId, $buildingId),
            'expensesByCategory' => $this->reportService->getExpensesByCategoryReportFiltered($landlordId, $dateRange, $buildingId),
            'waterConsumption' => $hasWaterAccess
                ? $this->reportService->getWaterConsumptionReportFiltered($landlordId, $dateRange, $buildingId)
                : null,
            'topPerformingUnits' => $this->reportService->getTopPerformingUnitsReportFiltered($landlordId, $dateRange, $buildingId),
        ];

        $previousPeriodData = null;
        if ($compare) {
            $previousDateRange = $this->reportService->getPreviousPeriodDateRange($dateRange);
            $previousPeriodData = [
                'totals' => $this->reportService->getReportTotals($landlordId, $previousDateRange, $buildingId),
            ];
        }

        $currentTotals = $this->reportService->getReportTotals($landlordId, $dateRange, $buildingId);

        return $this->renderFinances('reports', array_merge($currentData, [
            'totals' => $currentTotals,
            'previousPeriodData' => $previousPeriodData,
            'featureAccess' => ['water_billing' => $hasWaterAccess],
            'filters' => [
                'period' => $period,
                'building_id' => $buildingId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'compare' => $compare,
            ],
        ]));
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = [
            'landlord_id' => $this->getLandlordId(),
            'period' => (int) $request->query('period', '12'),
        ];

        return $this->exportService->exportReports($filters, $request->query('format', 'xlsx'));
    }

    /**
     * Phase-100 REPORTS-DEPTH-3: the rent roll — a point-in-time tenancy snapshot
     * (no period), downloadable as pdf/xlsx/csv. Sourced from RentRollService so all
     * three formats agree.
     */
    public function rentRoll(Request $request, \App\Services\RentRollService $rentRoll): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,xlsx,csv',
            'building_id' => 'nullable|integer',
            'property_id' => 'nullable|integer',
        ]);

        $landlordId = $this->getLandlordId();
        $format = $validated['format'] ?? 'xlsx';
        $buildingId = $validated['building_id'] ?? null;
        $propertyId = $validated['property_id'] ?? null;

        $currency = \App\Models\PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? \App\Enums\Currency::default();
        $filename = 'rent_roll_'.now()->format('Y_m_d');

        if ($format === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\RentRollExport($landlordId, $currency->value, $buildingId, $propertyId),
                $filename.'.xlsx',
            );
        }

        $data = $rentRoll->forLandlord($landlordId, $buildingId, $propertyId);

        if ($format === 'pdf') {
            $user = auth()->user();

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.rent-roll', [
                'data' => $data,
                'landlord' => $user->isScopeOwner() ? $user : $user->landlord,
                'generated_at' => now()->format('F j, Y g:i A'),
                'currency_symbol' => $currency->symbol(),
                'currency_code' => $currency->value,
            ])->download($filename.'.pdf');
        }

        return \Illuminate\Support\Facades\Response::make(
            $this->rentRollCsv($data, $currency->symbol()),
            200,
            ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"'],
        );
    }

    /**
     * Phase-100 REPORTS-DEPTH-3: per-property P&L for a period (pdf/xlsx/csv), sourced
     * from PropertyPnlService. Period is resolved the same way as the reports tab.
     */
    public function propertyPnl(Request $request, \App\Services\PropertyPnlService $pnl): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:pdf,xlsx,csv',
            'period' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'property_id' => 'nullable|integer',
        ]);

        $landlordId = $this->getLandlordId();
        $format = $validated['format'] ?? 'xlsx';
        $propertyId = $validated['property_id'] ?? null;

        $range = $this->reportService->getReportDateRange(
            $validated['period'] ?? '12',
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
            $landlordId,
        );
        $start = \Illuminate\Support\Carbon::parse($range['start']);
        $end = \Illuminate\Support\Carbon::parse($range['end']);

        $currency = \App\Models\PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? \App\Enums\Currency::default();
        $filename = 'property_pnl_'.$start->format('Y_m_d');

        if ($format === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\PropertyPnlExport($landlordId, $start, $end, $currency->value, $propertyId),
                $filename.'.xlsx',
            );
        }

        $data = $pnl->forLandlord($landlordId, $start, $end, $propertyId);

        if ($format === 'pdf') {
            $user = auth()->user();

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.property-pnl', [
                'data' => $data,
                'landlord' => $user->isScopeOwner() ? $user : $user->landlord,
                'generated_at' => now()->format('F j, Y g:i A'),
                'currency_symbol' => $currency->symbol(),
                'currency_code' => $currency->value,
            ])->download($filename.'.pdf');
        }

        return \Illuminate\Support\Facades\Response::make(
            $this->propertyPnlCsv($data, $currency->symbol()),
            200,
            ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"'],
        );
    }

    /**
     * Phase-100 REPORTS-DEPTH-3: a per-property owner statement (PDF) for a period — the
     * document a PM hands the owner. 404s on a property that isn't this landlord's.
     */
    public function ownerStatement(Request $request, \App\Services\OwnerStatementService $statements): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'property_id' => 'required|integer',
            'period' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $landlordId = $this->getLandlordId();

        $range = $this->reportService->getReportDateRange(
            $validated['period'] ?? '12',
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
            $landlordId,
        );
        $start = \Illuminate\Support\Carbon::parse($range['start']);
        $end = \Illuminate\Support\Carbon::parse($range['end']);

        $data = $statements->forProperty($landlordId, (int) $validated['property_id'], $start, $end);
        abort_if($data === null, 404);

        $currency = \App\Models\PaymentConfiguration::where('landlord_id', $landlordId)->first()?->default_currency
            ?? \App\Enums\Currency::default();
        $user = auth()->user();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.owner-statement', [
            'data' => $data,
            'landlord' => $user->isScopeOwner() ? $user : $user->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
            'currency_symbol' => $currency->symbol(),
            'currency_code' => $currency->value,
        ])->download('owner_statement_'.\Illuminate\Support\Str::slug($data['property']['name']).'_'.$start->format('Y_m_d').'.pdf');
    }

    private function propertyPnlCsv(array $data, string $symbol): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, [$data['title']]);
        fputcsv($out, ['Period: '.$data['period']['start'].' to '.$data['period']['end']]);
        fputcsv($out, ['Generated: '.$data['generated_at']]);
        fputcsv($out, []);

        fputcsv($out, ['Property', 'Collected', 'Expenses', 'Net', 'Margin %']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, [
                $row['property'],
                $symbol.' '.number_format($row['collected'], 2),
                $symbol.' '.number_format($row['expenses'], 2),
                $symbol.' '.number_format($row['net'], 2),
                $row['margin'].'%',
            ]);
        }
        $t = $data['totals'];
        fputcsv($out, [
            'TOTAL ('.$t['properties'].' properties)',
            $symbol.' '.number_format($t['collected'], 2),
            $symbol.' '.number_format($t['expenses'], 2),
            $symbol.' '.number_format($t['net'], 2),
            $t['margin'].'%',
        ]);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function rentRollCsv(array $data, string $symbol): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, [$data['title']]);
        fputcsv($out, ['Generated: '.$data['generated_at']]);
        fputcsv($out, []);

        $t = $data['totals'];
        fputcsv($out, ['Summary']);
        fputcsv($out, ['Units', $t['units']]);
        fputcsv($out, ['Occupied', $t['occupied']]);
        fputcsv($out, ['Vacant', $t['vacant']]);
        fputcsv($out, ['Expiring (<=60d)', $t['expiring']]);
        fputcsv($out, ['Occupancy Rate', $t['occupancy_rate'].'%']);
        fputcsv($out, ['Monthly Rent', $symbol.' '.number_format($t['monthly_rent'], 2)]);
        fputcsv($out, ['Deposits Held', $symbol.' '.number_format($t['deposits_held'], 2)]);
        fputcsv($out, ['Outstanding', $symbol.' '.number_format($t['outstanding'], 2)]);
        fputcsv($out, []);

        fputcsv($out, ['Property', 'Building', 'Unit', 'Status', 'Tenant', 'Rent', 'Deposit Held', 'Wallet Credit', 'Outstanding', 'Lease Start', 'Lease End']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, [
                $row['property'] ?? 'N/A',
                $row['building'] ?? 'N/A',
                $row['unit'],
                ucfirst($row['status']),
                $row['tenant'] ?? '-',
                $symbol.' '.number_format($row['rent'], 2),
                $symbol.' '.number_format($row['deposit_held'], 2),
                $symbol.' '.number_format($row['wallet_credit'], 2),
                $symbol.' '.number_format($row['outstanding'], 2),
                $row['lease_start'] ?? '-',
                $row['lease_end'] ?? '-',
            ]);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
