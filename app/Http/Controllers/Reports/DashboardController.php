<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreDashboardRequest;
use App\Models\LandlordDashboard;
use App\Models\ReportMetric;
use App\Models\SavedReport;
use App\Services\Reports\DashboardCardRegistry;
use App\Services\Reports\DashboardPdfService;
use App\Services\Reports\DashboardService;
use App\Services\Reports\XlsxExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Phase-50 LANDLORD-DASHBOARDS-3 (show) + Phase-73 DASHBOARD-EDITOR (CRUD).
 *
 * Dashboards are TenantScope-bound; numeric {dashboard} routes use route-model
 * binding (404s foreign rows) while show resolves per-landlord by slug. Every
 * posted layout is re-validated card-by-card for landlord ownership via
 * DashboardService::validateLayout — the layout JSON is never trusted on write.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboards,
        private DashboardCardRegistry $cardRegistry,
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $dashboards = LandlordDashboard::query()
            ->where('landlord_id', $landlordId)
            ->where('slug', '!=', \App\Http\Controllers\DashboardPreferenceController::MAIN_DASHBOARD_SLUG)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'description', 'is_default', 'layout'])
            ->map(fn (LandlordDashboard $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'description' => $d->description,
                'is_default' => $d->is_default,
                'card_count' => is_array($d->layout) ? count($d->layout) : 0,
            ]);

        return Inertia::render('Dashboards/Index', ['dashboards' => $dashboards]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Dashboards/Editor', [
            'dashboard' => null,
            'savedReports' => $this->savedReportsFor($request),
            'metrics' => $this->metricsFor($request),
            'cardTypes' => $this->cardRegistry->descriptors(),
        ]);
    }

    public function store(StoreDashboardRequest $request): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);
        $data = $request->validated();

        $layout = $this->dashboards->validateLayout($data['layout'], $landlordId);

        $dashboard = DB::transaction(function () use ($data, $layout, $landlordId) {
            $dashboard = LandlordDashboard::create([
                'landlord_id' => $landlordId,
                'slug' => $this->uniqueSlug($data['name'], $landlordId),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'layout' => $layout,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            if ($dashboard->is_default) {
                $this->makeSoleDefault($dashboard, $landlordId);
            }

            return $dashboard;
        });

        return redirect()->route('dashboards.show', $dashboard->slug)
            ->with('success', __('reports.dashboards.created'));
    }

    public function edit(Request $request, LandlordDashboard $dashboard): Response
    {
        return Inertia::render('Dashboards/Editor', [
            'dashboard' => [
                'id' => $dashboard->id,
                'slug' => $dashboard->slug,
                'name' => $dashboard->name,
                'description' => $dashboard->description,
                'layout' => $dashboard->layout ?? [],
                'is_default' => $dashboard->is_default,
            ],
            'savedReports' => $this->savedReportsFor($request),
            'metrics' => $this->metricsFor($request),
            'cardTypes' => $this->cardRegistry->descriptors(),
        ]);
    }

    public function update(StoreDashboardRequest $request, LandlordDashboard $dashboard): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);
        $data = $request->validated();

        $layout = $this->dashboards->validateLayout($data['layout'], $landlordId);

        DB::transaction(function () use ($dashboard, $data, $layout, $landlordId) {
            $dashboard->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'layout' => $layout,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            if ($dashboard->is_default) {
                $this->makeSoleDefault($dashboard, $landlordId);
            }
        });

        return redirect()->route('dashboards.show', $dashboard->slug)
            ->with('success', __('reports.dashboards.updated'));
    }

    public function destroy(LandlordDashboard $dashboard): RedirectResponse
    {
        $dashboard->delete();

        return redirect()->route('dashboards.index')
            ->with('success', __('reports.dashboards.deleted'));
    }

    public function setDefault(Request $request, LandlordDashboard $dashboard): RedirectResponse
    {
        DB::transaction(fn () => $this->makeSoleDefault($dashboard, $this->landlordIdFor($request)));

        return redirect()->route('dashboards.index')
            ->with('success', __('reports.dashboards.default_set'));
    }

    public function preview(Request $request): JsonResponse
    {
        $landlordId = $this->landlordIdFor($request);
        $layout = $request->input('layout', []);
        abort_unless(is_array($layout), 422);

        $transient = new LandlordDashboard([
            'landlord_id' => $landlordId,
            'slug' => 'preview',
            'name' => (string) $request->input('name', 'Preview'),
            'layout' => $layout,
        ]);
        $transient->landlord_id = $landlordId;

        try {
            return response()->json($this->dashboards->buildPayload($transient));
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show(Request $request, string $slug): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $dashboard = LandlordDashboard::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Dashboards/Show', [
            'payload' => $this->dashboards->buildPayload($dashboard),
        ]);
    }

    /**
     * Phase-74 DASH-EXPORT-1: stream the dashboard as a PDF (owner only —
     * route-model binding 404s a foreign dashboard via TenantScope).
     */
    public function exportPdf(LandlordDashboard $dashboard, DashboardPdfService $pdf): SymfonyResponse
    {
        return $pdf->render($dashboard)->download(Str::slug($dashboard->name ?: 'dashboard').'.pdf');
    }

    /**
     * Phase-74 DASH-EXPORT-2: one xlsx sheet per data card; metric/kpi cards
     * collapse to a single summary sheet.
     */
    public function exportXlsx(LandlordDashboard $dashboard, XlsxExportService $xlsx): BinaryFileResponse
    {
        $cards = $this->dashboards->buildPayload($dashboard)['cards'];
        $sheets = [];
        $summary = [];

        foreach ($cards as $i => $card) {
            $rawTitle = ($card['title'] ?? '') !== '' ? (string) $card['title'] : ucfirst((string) $card['type']);
            // PhpSpreadsheet rejects * : / \ ? [ ] in sheet titles; card titles
            // are free-text, so strip them before the 31-char cap.
            $title = mb_substr(($i + 1).' '.str_replace(['*', ':', '/', '\\', '?', '[', ']'], '-', $rawTitle), 0, 31);

            if ($card['type'] === 'saved_report' && ($card['rows'] ?? []) !== []) {
                $sheets[] = [
                    'title' => $title,
                    'columns' => array_map(
                        fn ($k) => ['label' => (string) $k, 'key' => (string) $k, 'type' => 'string'],
                        array_keys($card['rows'][0]),
                    ),
                    'rows' => $card['rows'],
                ];
            } elseif ($card['type'] === 'chart') {
                $sheets[] = [
                    'title' => $title,
                    'columns' => [
                        ['label' => 'Label', 'key' => 'label', 'type' => 'string'],
                        ['label' => 'Value', 'key' => 'value', 'type' => 'integer'],
                    ],
                    'rows' => $card['points'] ?? [],
                ];
            } elseif (in_array($card['type'], ['metric', 'kpi'], true)) {
                $summary[] = [
                    'card' => (string) ($card['title'] ?? ''),
                    'value' => $card['type'] === 'kpi' ? ($card['value'] ?? '') : ($card['average'] ?? ''),
                    'unit' => (string) ($card['unit'] ?? ''),
                ];
            }
        }

        if ($summary !== []) {
            $sheets[] = [
                'title' => 'Metrics',
                'columns' => [
                    ['label' => 'Card', 'key' => 'card', 'type' => 'string'],
                    ['label' => 'Value', 'key' => 'value', 'type' => 'string'],
                    ['label' => 'Unit', 'key' => 'unit', 'type' => 'string'],
                ],
                'rows' => $summary,
            ];
        }

        if ($sheets === []) {
            $sheets[] = ['title' => 'Dashboard', 'columns' => [], 'rows' => []];
        }

        $tmpDir = storage_path('app/tmp/dashboard-exports');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0o755, true);
        }
        $path = $tmpDir.'/'.$dashboard->id.'-'.now()->format('YmdHis').'.xlsx';
        $xlsx->writeMultiSheet($sheets, $path);

        return response()->download($path, Str::slug($dashboard->name ?: 'dashboard').'.xlsx')->deleteFileAfterSend();
    }

    private function makeSoleDefault(LandlordDashboard $dashboard, int $landlordId): void
    {
        LandlordDashboard::query()
            ->where('landlord_id', $landlordId)
            ->where('id', '!=', $dashboard->id)
            ->update(['is_default' => false]);

        if (! $dashboard->is_default) {
            $dashboard->forceFill(['is_default' => true])->save();
        }
    }

    private function uniqueSlug(string $name, int $landlordId, ?int $ignoreId = null): string
    {
        // Cap the base well under the slug column's 64 chars (leaving room for a
        // -N suffix) so a long name can't overflow into a QueryException.
        $base = Str::limit(Str::slug($name), 50, '') ?: 'dashboard';
        $slug = $base;
        $n = 1;

        // Reserved literals are registered before /dashboards/{slug}, so a slug
        // of 'create'/'preview' would shadow the show page — force a suffix.
        // withTrashed() so we don't collide with a soft-deleted row's slug
        // (the unique index counts trashed rows).
        $reserved = ['create', 'preview', 'preferences', 'shares', 'share'];

        while (in_array($slug, $reserved, true)
            || LandlordDashboard::query()
                ->withTrashed()
                ->withoutGlobalScope('landlord')
                ->where('landlord_id', $landlordId)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id:int, name:string}>
     */
    private function savedReportsFor(Request $request)
    {
        return SavedReport::query()
            ->where('landlord_id', $this->landlordIdFor($request))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SavedReport $r) => ['id' => $r->id, 'name' => $r->name]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{slug:string, name:string, unit:?string}>
     */
    private function metricsFor(Request $request)
    {
        return ReportMetric::query()
            ->where('landlord_id', $this->landlordIdFor($request))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug', 'name', 'unit'])
            ->map(fn (ReportMetric $m) => ['slug' => $m->slug, 'name' => $m->name, 'unit' => $m->unit]);
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->effectiveScopeId();
    }
}
