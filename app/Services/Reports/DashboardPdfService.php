<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\LandlordDashboard;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

/**
 * Phase-74 DASH-EXPORT: render a landlord dashboard to PDF (dompdf) for an
 * owner/board pack. Reuses DashboardService::buildPayload (the single render
 * path) so the export shows exactly what the dashboard shows.
 */
class DashboardPdfService
{
    public function __construct(
        private DashboardService $dashboards,
    ) {}

    public function render(LandlordDashboard $dashboard): PdfInstance
    {
        $cards = $this->dashboards->buildPayload($dashboard)['cards'];

        return Pdf::loadView('dashboards.pdf', [
            'dashboardName' => $dashboard->name,
            'cards' => $cards,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');
    }
}
