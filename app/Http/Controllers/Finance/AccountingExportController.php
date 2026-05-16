<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\Accounting\AccountingExportService;
use App\Services\Accounting\AccountMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase-30 INT-ACCT-EXPORT-3: accountant-facing UI + endpoints for
 * the QuickBooks IIF / Sage CSV export. The index page surfaces
 * mapping diagnostics from AccountMappingService so the landlord
 * sees missing GL mappings BEFORE they hit "export" — a broken
 * export is worse than a slow one.
 */
class AccountingExportController extends Controller
{
    public function __construct(
        private readonly AccountingExportService $exporter,
        private readonly AccountMappingService $mapper,
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = (int) $request->user()->id;

        return Inertia::render('Finances/Accounting/Export', [
            'diagnostics' => $this->mapper->mappingDiagnostics($landlordId),
            'accountCount' => ChartOfAccount::query()
                ->where('landlord_id', $landlordId)
                ->where('is_active', true)
                ->count(),
            'formats' => AccountingExportService::FORMATS,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'required|string|in:'.implode(',', AccountingExportService::FORMATS),
        ]);

        $landlordId = (int) $request->user()->id;

        return $this->exporter->export(
            landlordId: $landlordId,
            from: CarbonImmutable::parse($validated['from']),
            to: CarbonImmutable::parse($validated['to']),
            format: $validated['format'],
        );
    }
}
