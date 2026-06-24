<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportMetric;
use App\Services\Reports\MetricFormulaService;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-50 CUSTOM-METRICS-3: thin REST surface for landlord-owned
 * report metrics. The service does all the parsing + validation; this
 * controller just routes input and returns JSON or a redirect.
 *
 * Routes (role:landlord):
 *   GET    /reports/metrics              → JSON list of active metrics
 *   POST   /reports/metrics              → parse expression + persist
 *   DELETE /reports/metrics/{metric}     → soft-delete
 *
 * The store action is the only place an expression is parsed for the
 * first time. The cached parsed_rpn is what every subsequent run
 * evaluates — the raw expression is never re-parsed at run time.
 */
class ReportMetricController extends Controller
{
    public function __construct(
        private MetricFormulaService $formulas,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $landlordId = $this->landlordIdFor($request);

        $rows = ReportMetric::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'expression', 'unit']);

        return response()->json(['metrics' => $rows]);
    }

    /**
     * Phase-73 METRICS-DEPTH-2: the metrics author/manage page. Lists the
     * landlord's metrics + the numeric field catalogue a formula may
     * reference (the same allow-list MetricFormulaService validates
     * against), so the editor can offer fields without hard-coding them.
     */
    public function manage(Request $request): Response
    {
        $landlordId = $this->landlordIdFor($request);

        $metrics = ReportMetric::query()
            ->withoutGlobalScope('landlord')
            ->where('landlord_id', $landlordId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'expression', 'unit']);

        return Inertia::render('Reports/Metrics', [
            'metrics' => $metrics,
            'fields' => $this->numericFieldCatalogue(),
        ]);
    }

    /**
     * Phase-73 METRICS-DEPTH-2: live, no-persist formula validation. The
     * editor posts the expression as the landlord types so a bad formula
     * (off-allow-list field, malformed, injection payload) surfaces its
     * exact message before save. Nothing touches the database.
     */
    public function validate(Request $request): JsonResponse
    {
        $expression = (string) $request->input('expression', '');

        try {
            $this->formulas->parse($expression);
        } catch (ValidationException $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->errors()['expression'][0] ?? 'Invalid expression.',
            ]);
        }

        return response()->json(['valid' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);

        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'expression' => ['required', 'string', 'max:1024'],
            'unit' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $name = (string) $request->input('name');
        $rawSlug = (string) ($request->input('slug') ?? Str::slug($name, '_'));
        Validator::make(['slug' => $rawSlug], [
            'slug' => [
                'required',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('report_metrics')->where(fn ($q) => $q->where('landlord_id', $landlordId)),
            ],
        ])->validate();

        $rpn = $this->formulas->parse((string) $request->input('expression'));

        $metric = ReportMetric::query()->create([
            'landlord_id' => $landlordId,
            'slug' => $rawSlug,
            'name' => $name,
            'expression' => (string) $request->input('expression'),
            'parsed_rpn' => $rpn,
            'unit' => $request->input('unit'),
        ]);

        return redirect()
            ->route('reports.metrics.manage')
            ->with('success', "Created metric: {$metric->name}");
    }

    public function destroy(Request $request, ReportMetric $metric): RedirectResponse
    {
        $landlordId = $this->landlordIdFor($request);
        if ($metric->landlord_id !== $landlordId) {
            abort(403, 'Metric does not belong to this landlord.');
        }
        $metric->delete();

        return redirect()
            ->route('reports.metrics.manage')
            ->with('success', "Deleted metric: {$metric->name}");
    }

    private function landlordIdFor(Request $request): int
    {
        $user = $request->user();

        return $user->effectiveScopeId();
    }

    /**
     * The numeric subset of the builder allow-list — the only fields a
     * metric formula may reference. Returned as {key,label} for the editor.
     *
     * @return list<array{key: string, label: string}>
     */
    private function numericFieldCatalogue(): array
    {
        $fields = [];
        foreach (ReportBuilderService::ALLOWED_FIELDS as $key => $meta) {
            if ($meta['type'] === 'numeric') {
                $fields[] = ['key' => $key, 'label' => $meta['label']];
            }
        }

        return $fields;
    }
}
