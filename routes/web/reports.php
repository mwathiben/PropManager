<?php

use Illuminate\Support\Facades\Route;

// Phase-27 BI-COHORT-1/2/3: tenant retention + acquisition + LTV.
// role:landlord — same scope as finances.reports; tenant-facing
// analytics surface is a separate Phase 27 finding (BI-BUILDER).
Route::middleware('role:landlord,manager')
    ->get('/reports/cohort', [\App\Http\Controllers\Reports\CohortController::class, 'index'])
    ->name('reports.cohort');

// Phase-27 BI-NOI-1/2/3: NOI per property + cap rate + expense allocation.
Route::middleware('role:landlord,manager')
    ->get('/reports/noi', [\App\Http\Controllers\Reports\NoiController::class, 'index'])
    ->name('reports.noi');

// Phase-27 BI-FORECAST-1/2/3: rent-roll forecast + seasonality + vacancy.
Route::middleware('role:landlord,manager')
    ->get('/reports/forecast', [\App\Http\Controllers\Reports\ForecastController::class, 'index'])
    ->name('reports.forecast');

// Phase-27 BI-BUILDER-1/2/3: saved-report library + drag-drop builder.
// The SAFE SQL generator (ReportBuilderService) is the security-critical
// path here — see Phase27BuilderInjectionTest.
Route::middleware('role:landlord,manager')->prefix('reports/builder')->name('reports.builder.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\BuilderController::class, 'index'])->name('index');
    Route::post('/run', [\App\Http\Controllers\Reports\BuilderController::class, 'run'])->name('run');
    Route::post('/', [\App\Http\Controllers\Reports\BuilderController::class, 'store'])->name('store');
    Route::delete('/{report}', [\App\Http\Controllers\Reports\BuilderController::class, 'destroy'])->name('destroy');
    // Phase-50 DRILL-DOWN-3: navigate from a parent report row to the
    // filtered child synthesised by DrillDownService.
    Route::get('/{report}/drill', [\App\Http\Controllers\Reports\BuilderController::class, 'drill'])->name('drill');
});

// Phase-50 TEMPLATE-MARKETPLACE-3: platform-curated report templates
// gallery + one-click clone into a per-landlord SavedReport.
Route::middleware('role:landlord,manager')->prefix('reports/templates')->name('reports.templates.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\ReportTemplateController::class, 'index'])->name('index');
    Route::post('/{template}/clone', [\App\Http\Controllers\Reports\ReportTemplateController::class, 'clone'])->name('clone');
});

// Phase-50 CUSTOM-METRICS-3: landlord-defined formulas evaluated by
// MetricFormulaService and surfaced as derived columns in the builder.
Route::middleware('role:landlord,manager')->prefix('reports/metrics')->name('reports.metrics.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\ReportMetricController::class, 'index'])->name('index');
    // Phase-73 METRICS-DEPTH-2: the author/manage page + live no-persist
    // formula validation. Both registered before the {metric} delete so
    // /manage + /validate never bind as a numeric metric id.
    Route::get('/manage', [\App\Http\Controllers\Reports\ReportMetricController::class, 'manage'])->name('manage');
    Route::post('/validate', [\App\Http\Controllers\Reports\ReportMetricController::class, 'validate'])->name('validate');
    Route::post('/', [\App\Http\Controllers\Reports\ReportMetricController::class, 'store'])->name('store');
    Route::delete('/{metric}', [\App\Http\Controllers\Reports\ReportMetricController::class, 'destroy'])
        ->whereNumber('metric')->name('destroy');
});

// Phase-73 DASHBOARD-EDITOR: landlord-defined dashboards CRUD + card editor
// + live preview. Registered BEFORE the {slug} show route so /dashboards,
// /dashboards/create + /dashboards/preview aren't shadowed by {slug}.
Route::middleware('role:landlord,manager')->prefix('dashboards')->name('dashboards.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\DashboardController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\Reports\DashboardController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Reports\DashboardController::class, 'store'])->name('store');
    Route::post('/preview', [\App\Http\Controllers\Reports\DashboardController::class, 'preview'])->name('preview');
    // Phase-74 DASH-SHARE: signed dashboard links. Registered before the
    // {slug} show route so /dashboards/shares isn't bound as a slug.
    Route::get('/shares', [\App\Http\Controllers\Reports\DashboardShareController::class, 'index'])->name('shares.index');
    Route::post('/shares', [\App\Http\Controllers\Reports\DashboardShareController::class, 'store'])->name('shares.store');
    Route::post('/shares/{share}/revoke', [\App\Http\Controllers\Reports\DashboardShareController::class, 'revoke'])
        ->whereNumber('share')->name('shares.revoke');
    Route::get('/{dashboard}/edit', [\App\Http\Controllers\Reports\DashboardController::class, 'edit'])->whereNumber('dashboard')->name('edit');
    Route::put('/{dashboard}', [\App\Http\Controllers\Reports\DashboardController::class, 'update'])->whereNumber('dashboard')->name('update');
    Route::delete('/{dashboard}', [\App\Http\Controllers\Reports\DashboardController::class, 'destroy'])->whereNumber('dashboard')->name('destroy');
    Route::post('/{dashboard}/default', [\App\Http\Controllers\Reports\DashboardController::class, 'setDefault'])->whereNumber('dashboard')->name('set-default');
    // Phase-74 DASH-EXPORT: owner-only PDF + XLSX export of a dashboard.
    Route::get('/{dashboard}/export/pdf', [\App\Http\Controllers\Reports\DashboardController::class, 'exportPdf'])
        ->whereNumber('dashboard')->middleware('throttle:pdf-render')->name('export-pdf');
    Route::get('/{dashboard}/export/xlsx', [\App\Http\Controllers\Reports\DashboardController::class, 'exportXlsx'])
        ->whereNumber('dashboard')->middleware('throttle:export')->name('export-xlsx');
});

// Phase-50 LANDLORD-DASHBOARDS-3: composable dashboard show route.
// Slug is per-landlord — the controller scopes by (landlord_id, slug).
Route::middleware('role:landlord,manager')
    ->get('/dashboards/{slug}', [\App\Http\Controllers\Reports\DashboardController::class, 'show'])
    ->name('dashboards.show');

// Phase-55 WIDGET-ORDERING-1: persist landlord widget order via the
// Phase-50 landlord_dashboards.layout JSON column (slug='main_dashboard').
Route::middleware('role:landlord,manager')
    ->patch('/dashboards/preferences', [\App\Http\Controllers\DashboardPreferenceController::class, 'update'])
    ->name('dashboards.preferences.update');

// Phase-74 CROSS-BUILDING: persist the main dashboard's building scope
// (active_building | all_buildings) on the same main_dashboard pref row.
Route::middleware('role:landlord,manager')
    ->patch('/dashboards/scope', [\App\Http\Controllers\DashboardPreferenceController::class, 'updateScope'])
    ->name('dashboard.scope.update');

// Phase-54 COST-UI-2: landlord-only manual cost entry. parts category
// auto-recorded via Phase 49 TicketResolutionService::recordParts; this
// endpoint accepts vendor|labor|other only (validator enforces).
Route::middleware('role:landlord,manager')
    ->post('/tickets/{ticket}/costs', [\App\Http\Controllers\TicketCostController::class, 'store'])
    ->name('tickets.costs.store');

// Phase-54 PARTS-REORDER-3: landlord-facing surface over the
// draft_purchase_orders the parts:reorder-suggest cron materialises.
Route::middleware('role:landlord,manager')->prefix('parts/purchase-orders')->name('parts.purchase-orders.')->group(function () {
    Route::get('/', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'index'])->name('index');
    Route::post('/{order}/confirm', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'confirm'])->name('confirm');
    Route::post('/{order}/cancel', [\App\Http\Controllers\PartsPurchaseOrderController::class, 'cancel'])->name('cancel');
});

// Phase-75 PARTS-PRICING-2/3: per-part supplier catalogue + pricing surface.
Route::middleware('role:landlord,manager')->group(function () {
    Route::get('/parts/pricing', [\App\Http\Controllers\PartPricingController::class, 'index'])
        ->name('parts.pricing');
    Route::post('/parts/{part}/suppliers', [\App\Http\Controllers\PartSupplierController::class, 'store'])
        ->whereNumber('part')->name('parts.suppliers.store');
    Route::delete('/parts/{part}/suppliers/{supplier}', [\App\Http\Controllers\PartSupplierController::class, 'destroy'])
        ->whereNumber('part')->whereNumber('supplier')->name('parts.suppliers.destroy');
});

// Phase-54 SLA-LANDLORD-UI-1: landlord-scoped CRUD over SLA overrides.
// NOT under /admin — that namespace is super-admin only. Landlord
// overrides + global defaults coexist via the Phase-49 cascade in
// SlaDefinitionService::resolveFor.
Route::middleware('role:landlord,manager')->prefix('sla')->name('sla.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SlaDefinitionController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\SlaDefinitionController::class, 'store'])->name('store');
    Route::patch('/{sla}', [\App\Http\Controllers\SlaDefinitionController::class, 'update'])->name('update');
    Route::delete('/{sla}', [\App\Http\Controllers\SlaDefinitionController::class, 'destroy'])->name('destroy');
});

// Phase-73 REPORT-SHARE: landlord mints/lists/revokes signed report links.
Route::middleware('role:landlord,manager')->prefix('reports/shares')->name('reports.shares.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\ReportShareController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Reports\ReportShareController::class, 'store'])->name('store');
    Route::post('/{share}/revoke', [\App\Http\Controllers\Reports\ReportShareController::class, 'revoke'])
        ->whereNumber('share')->name('revoke');
});

// Phase-27 BI-DELIVERY-2/3: scheduled report delivery self-serve.
Route::middleware('role:landlord,manager')->prefix('reports/scheduled')->name('reports.scheduled.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Reports\ScheduledController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\Reports\ScheduledController::class, 'store'])->name('store');
    // Phase-50 REAL-TIME-PREVIEW-2: same payload the next send would
    // carry; cross-tenant saved_report_id 403s at the controller.
    // Registered before the {schedule} routes so /preview never binds.
    Route::post('/preview', [\App\Http\Controllers\Reports\ScheduledController::class, 'preview'])->name('preview');
    // Phase-73 SCHEDULED-DEPTH: edit cadence/recipient + pause/resume.
    Route::put('/{schedule}', [\App\Http\Controllers\Reports\ScheduledController::class, 'update'])
        ->whereNumber('schedule')->name('update');
    Route::post('/{schedule}/toggle-pause', [\App\Http\Controllers\Reports\ScheduledController::class, 'togglePause'])
        ->whereNumber('schedule')->name('toggle-pause');
    Route::delete('/{schedule}', [\App\Http\Controllers\Reports\ScheduledController::class, 'destroy'])
        ->whereNumber('schedule')->name('destroy');
});
