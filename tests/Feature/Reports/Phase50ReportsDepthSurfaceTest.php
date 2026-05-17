<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Controllers\Reports\DashboardController;
use App\Http\Controllers\Reports\ReportMetricController;
use App\Http\Controllers\Reports\ReportTemplateController;
use App\Models\LandlordDashboard;
use App\Models\ReportMetric;
use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\DashboardService;
use App\Services\Reports\DrillDownService;
use App\Services\Reports\MetricFormulaService;
use App\Services\Reports\ReportTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-50 CI-1: consolidated REPORTS-DEPTH surface watchdog.
 *
 * One test class asserts every structural piece of phase-50 still
 * exists: tables + columns, routes, controllers, services, models, and
 * the runbook + alert rows. If the build later removes / renames one
 * of these, this watchdog fails fast so reviewers see the regression
 * before merge.
 */
class Phase50ReportsDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- DRILL-DOWN --------------------------------------------------------

    public function test_saved_reports_has_drill_down_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('saved_reports', 'parent_report_id'));
        $this->assertTrue(Schema::hasColumn('saved_reports', 'drill_field'));
    }

    public function test_saved_report_parent_and_children_relations_exist(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $parent = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'Parent',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
            'drill_field' => 'payment.payment_method',
        ]);
        $child = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'Child',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
            'parent_report_id' => $parent->id,
        ]);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->children);
    }

    public function test_drill_down_service_appends_filter(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $parent = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'Parent',
            'config' => [
                'table' => 'payments',
                'fields' => ['payment.amount', 'payment.payment_method'],
                'filters' => [],
                'limit' => 50,
            ],
            'drill_field' => 'payment.payment_method',
        ]);

        $result = app(DrillDownService::class)->resolveChild($parent, 'mpesa');
        $this->assertSame('mpesa', $result['config']['filters'][0]['value']);
        $this->assertSame('payment.payment_method', $result['config']['filters'][0]['field']);
    }

    public function test_drill_route_exists(): void
    {
        $this->assertTrue(Route::has('reports.builder.drill'));
    }

    // -- TEMPLATE-MARKETPLACE ---------------------------------------------

    public function test_report_templates_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('report_templates'));
        foreach (['slug', 'name', 'category', 'config', 'is_active', 'sort_order'] as $col) {
            $this->assertTrue(Schema::hasColumn('report_templates', $col));
        }
    }

    public function test_report_template_seeder_populates_curated_templates(): void
    {
        $this->assertGreaterThanOrEqual(12, ReportTemplate::query()->count());
    }

    public function test_report_template_service_clone_for_creates_saved_report(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $template = ReportTemplate::active()->first();
        $report = app(ReportTemplateService::class)->cloneFor($landlord, $template);

        $this->assertInstanceOf(SavedReport::class, $report);
        $this->assertSame($landlord->id, $report->landlord_id);
        $this->assertSame($template->config, $report->config);
    }

    public function test_report_template_routes_exist(): void
    {
        $this->assertTrue(Route::has('reports.templates.index'));
        $this->assertTrue(Route::has('reports.templates.clone'));
    }

    public function test_report_template_controller_exists(): void
    {
        $this->assertTrue(class_exists(ReportTemplateController::class));
    }

    // -- CUSTOM-METRICS ----------------------------------------------------

    public function test_report_metrics_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('report_metrics'));
        foreach (['landlord_id', 'slug', 'name', 'expression', 'parsed_rpn', 'unit', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('report_metrics', $col));
        }
    }

    public function test_metric_formula_service_parses_safely(): void
    {
        $svc = app(MetricFormulaService::class);
        $rpn = $svc->parse('{invoice.amount_paid} / {invoice.total_due} * 100');
        $value = $svc->evaluate($rpn, [
            'invoice.amount_paid' => 750.0,
            'invoice.total_due' => 1000.0,
        ]);
        $this->assertEqualsWithDelta(75.0, $value, 1e-9);
    }

    public function test_metric_formula_service_rejects_classic_injection_payloads(): void
    {
        $svc = app(MetricFormulaService::class);
        foreach ([
            'system("rm")',
            '${jndi:ldap://x}',
            '`whoami`',
            "1; DROP TABLE users",
            '{users.password}',
            '{invoice.status} + 1',
            'eval("phpinfo()")',
            '1++2',
            str_repeat('1+', 100).'1',
        ] as $payload) {
            try {
                $svc->parse($payload);
                $this->fail("Payload should have been rejected: {$payload}");
            } catch (\Illuminate\Validation\ValidationException) {
                // expected
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_report_metric_routes_exist(): void
    {
        $this->assertTrue(Route::has('reports.metrics.index'));
        $this->assertTrue(Route::has('reports.metrics.store'));
        $this->assertTrue(Route::has('reports.metrics.destroy'));
    }

    public function test_report_metric_controller_exists(): void
    {
        $this->assertTrue(class_exists(ReportMetricController::class));
    }

    public function test_report_metric_model_has_tenant_scope(): void
    {
        $this->assertContains(
            \App\Traits\TenantScope::class,
            class_uses_recursive(ReportMetric::class),
        );
    }

    // -- REAL-TIME-PREVIEW -------------------------------------------------

    public function test_scheduled_preview_route_exists(): void
    {
        $this->assertTrue(Route::has('reports.scheduled.preview'));
    }

    // -- LANDLORD-DASHBOARDS -----------------------------------------------

    public function test_landlord_dashboards_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('landlord_dashboards'));
        foreach (['landlord_id', 'slug', 'name', 'layout', 'is_default'] as $col) {
            $this->assertTrue(Schema::hasColumn('landlord_dashboards', $col));
        }
    }

    public function test_dashboard_service_build_payload_renders_cards(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $report = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'Test Report',
            'config' => [
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'limit' => 10,
            ],
        ]);
        $dashboard = LandlordDashboard::create([
            'landlord_id' => $landlord->id,
            'slug' => 'home',
            'name' => 'Home',
            'layout' => [
                ['type' => 'saved_report', 'saved_report_id' => $report->id, 'size' => 'wide'],
            ],
        ]);

        $payload = app(DashboardService::class)->buildPayload($dashboard);
        $this->assertCount(1, $payload['cards']);
        $this->assertSame('saved_report', $payload['cards'][0]['type']);
        $this->assertSame($report->id, $payload['cards'][0]['saved_report_id']);
    }

    public function test_dashboard_service_rejects_cross_tenant_saved_report(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $reportA = SavedReport::create([
            'landlord_id' => $landlordA->id,
            'name' => 'A',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount']],
        ]);
        $dashboardB = LandlordDashboard::create([
            'landlord_id' => $landlordB->id,
            'slug' => 'home',
            'name' => 'Home',
            'layout' => [
                ['type' => 'saved_report', 'saved_report_id' => $reportA->id],
            ],
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(DashboardService::class)->buildPayload($dashboardB);
    }

    public function test_dashboards_show_route_exists(): void
    {
        $this->assertTrue(Route::has('dashboards.show'));
    }

    public function test_dashboard_controller_exists(): void
    {
        $this->assertTrue(class_exists(DashboardController::class));
    }

    // -- RUNBOOK + ALERTS --------------------------------------------------

    public function test_reports_runbook_exists(): void
    {
        $this->assertTrue(file_exists(base_path('docs/runbooks/reports.md')));
    }

    public function test_alert_thresholds_carries_report_render_failure_count(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('report_render_failure_count', $md);
    }
}
