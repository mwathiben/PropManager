<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Services\Reports\ReportTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-50 TEMPLATE-MARKETPLACE-3: landlord-facing template gallery.
 *
 * Two actions:
 *   - index — browse the active platform-curated templates grouped by
 *             category. Read-only.
 *   - clone — clone the selected template into a per-landlord
 *             SavedReport via ReportTemplateService::cloneFor; redirect
 *             to the builder so the landlord can run / edit the new
 *             report.
 *
 * Authorization piggybacks on SavedReportPolicy::create for the clone
 * action and role:landlord for both routes.
 */
class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templates,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', SavedReport::class);

        $rows = ReportTemplate::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'category', 'description']);

        return Inertia::render('Reports/Templates', [
            'templates' => $rows,
            'categories' => $rows->groupBy('category')->keys()->values(),
        ]);
    }

    public function clone(Request $request, ReportTemplate $template): RedirectResponse
    {
        $this->authorize('create', SavedReport::class);

        if (! $template->is_active) {
            abort(404, 'Template not available.');
        }

        $report = $this->templates->cloneFor(
            $request->user(),
            $template,
            $request->input('name'),
        );

        return redirect()
            ->route('reports.builder.index')
            ->with('success', "Cloned template into: {$report->name}");
    }
}
