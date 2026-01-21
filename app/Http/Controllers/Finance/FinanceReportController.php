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

        $hasWaterAccess = $user->isLandlord()
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
}
