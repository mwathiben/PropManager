<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-27 BI-BUILDER-1/2/3: custom report builder surface.
 *
 * Three actions:
 *   - index   — list saved reports + render the builder UI
 *   - run     — execute an ad-hoc config (returns JSON for the UI
 *               preview pane)
 *   - store   — persist a saved report (validates config end-to-end)
 *   - destroy — remove a saved report
 *
 * Authorization runs through SavedReportPolicy + role:landlord
 * middleware.
 */
class BuilderController extends Controller
{
    public function __construct(private ReportBuilderService $builder) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SavedReport::class);

        $reports = SavedReport::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'config', 'updated_at']);

        return Inertia::render('Reports/Builder', [
            'savedReports' => $reports,
            'allowedTables' => ReportBuilderService::ALLOWED_TABLES,
            'allowedFields' => ReportBuilderService::ALLOWED_FIELDS,
            'operatorMatrix' => [
                'numeric' => ReportBuilderService::NUMERIC_OPERATORS,
                'date' => ReportBuilderService::DATE_OPERATORS,
                'string' => ReportBuilderService::STRING_OPERATORS,
                'boolean' => ReportBuilderService::BOOLEAN_OPERATORS,
            ],
        ]);
    }

    public function run(Request $request)
    {
        $this->authorize('viewAny', SavedReport::class);

        $config = $this->parseConfig($request);
        $landlordId = $this->landlordIdFor($request);

        return response()->json([
            'rows' => $this->builder->run($config, $landlordId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SavedReport::class);

        $config = $this->parseConfig($request);
        $landlordId = $this->landlordIdFor($request);

        // Smoke-test the config by running it before saving — surfaces
        // ValidationException immediately if the user picked an invalid
        // combination. The empty result is fine.
        $this->builder->run($config, $landlordId);

        $report = SavedReport::create([
            'landlord_id' => $landlordId,
            'name' => (string) $request->input('name'),
            'description' => $request->input('description'),
            'config' => $config,
        ]);

        return redirect()->route('reports.builder.index')
            ->with('success', "Saved report: {$report->name}");
    }

    public function destroy(SavedReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);
        $report->delete();

        return redirect()->route('reports.builder.index')
            ->with('success', "Deleted: {$report->name}");
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->role === 'landlord' ? (int) $user->id : (int) $user->landlord_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseConfig(Request $request): array
    {
        $config = (array) $request->input('config', []);

        return [
            'table' => $config['table'] ?? null,
            'fields' => (array) ($config['fields'] ?? []),
            'filters' => (array) ($config['filters'] ?? []),
            'group_by' => (array) ($config['group_by'] ?? []),
            'sort_by' => (array) ($config['sort_by'] ?? []),
            'limit' => isset($config['limit']) ? (int) $config['limit'] : 200,
        ];
    }
}
