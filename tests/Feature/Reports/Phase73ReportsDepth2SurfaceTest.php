<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Controllers\Reports\DashboardController;
use App\Http\Controllers\Reports\ReportMetricController;
use App\Http\Controllers\Reports\ReportShareController;
use App\Http\Requests\Reports\StoreDashboardRequest;
use App\Models\ReportShare;
use App\Services\Reports\DashboardService;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-73 CI: consolidated REPORTS-DEPTH-2 surface watchdog. Asserts
 * every structural piece (tables/columns, routes, controllers, requests,
 * services, Vue pages, lang parity, the extended builder allow-list) still
 * exists so a later rename/removal fails fast at review.
 */
class Phase73ReportsDepth2SurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- tables + columns --------------------------------------------------

    public function test_report_shares_table_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('report_shares'));
        foreach (['landlord_id', 'saved_report_id', 'expires_at', 'revoked_at', 'last_viewed_at', 'view_count'] as $col) {
            $this->assertTrue(Schema::hasColumn('report_shares', $col), "report_shares.{$col} missing");
        }
    }

    public function test_scheduled_reports_has_paused_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('scheduled_reports', 'paused_at'));
    }

    // -- classes -----------------------------------------------------------

    public function test_controllers_requests_services_exist(): void
    {
        $this->assertTrue(class_exists(DashboardController::class));
        $this->assertTrue(class_exists(ReportShareController::class));
        $this->assertTrue(class_exists(ReportMetricController::class));
        $this->assertTrue(class_exists(StoreDashboardRequest::class));
        $this->assertTrue(method_exists(DashboardService::class, 'validateLayout'));
        $this->assertTrue(method_exists(ReportMetricController::class, 'validate'));
        $this->assertTrue(method_exists(ReportMetricController::class, 'manage'));
        $this->assertTrue(method_exists(ReportShareController::class, 'view'));
    }

    // -- routes ------------------------------------------------------------

    public function test_dashboard_routes_exist(): void
    {
        foreach (['index', 'create', 'store', 'update', 'destroy', 'preview', 'edit', 'set-default', 'show'] as $name) {
            $this->assertTrue(Route::has("dashboards.{$name}"), "dashboards.{$name} missing");
        }
    }

    public function test_report_share_routes_exist(): void
    {
        $this->assertTrue(Route::has('reports.shares.index'));
        $this->assertTrue(Route::has('reports.shares.store'));
        $this->assertTrue(Route::has('reports.shares.revoke'));
        $this->assertTrue(Route::has('reports.share.view'));
    }

    public function test_scheduled_depth_routes_exist(): void
    {
        $this->assertTrue(Route::has('reports.scheduled.update'));
        $this->assertTrue(Route::has('reports.scheduled.toggle-pause'));
    }

    public function test_metrics_depth_routes_exist(): void
    {
        $this->assertTrue(Route::has('reports.metrics.manage'));
        $this->assertTrue(Route::has('reports.metrics.validate'));
    }

    // -- Vue pages ---------------------------------------------------------

    public function test_vue_pages_exist_with_test_ids(): void
    {
        $cases = [
            'resources/js/Pages/Dashboards/Index.vue' => null,
            'resources/js/Pages/Dashboards/Editor.vue' => 'dashboard-editor',
            'resources/js/Pages/Reports/Shares.vue' => 'report-share',
            'resources/js/Pages/Reports/Scheduled.vue' => 'schedule-row',
            'resources/js/Pages/Reports/Metrics.vue' => 'metrics-editor',
            'resources/js/Pages/Finances/tabs/ReportsTab.vue' => 'report-tools',
        ];
        foreach ($cases as $path => $testId) {
            $full = base_path($path);
            $this->assertFileExists($full);
            if ($testId !== null) {
                $this->assertStringContainsString("data-testid=\"{$testId}\"", file_get_contents($full), "{$path} missing data-testid {$testId}");
            }
        }
    }

    // -- lang parity -------------------------------------------------------

    public function test_lang_blocks_have_matching_keys_across_locales(): void
    {
        $en = require base_path('lang/en/reports.php');
        $sw = require base_path('lang/sw/reports.php');
        $ar = require base_path('lang/ar/reports.php');

        foreach (['dashboards', 'share', 'scheduled', 'metrics'] as $block) {
            $this->assertArrayHasKey($block, $en);
            $this->assertSame(
                array_keys($en[$block]),
                array_keys($sw[$block] ?? []),
                "sw reports.{$block} key drift",
            );
            $this->assertSame(
                array_keys($en[$block]),
                array_keys($ar[$block] ?? []),
                "ar reports.{$block} key drift",
            );
        }
    }

    // -- extended allow-list ----------------------------------------------

    public function test_new_dimensions_present_in_allow_list(): void
    {
        foreach ([
            'payment.reconciliation_status',
            'invoice.rent_due',
            'invoice.arrears',
            'invoice.late_fees_total',
            'invoice.billing_period_start',
            'lease.end_date',
            'lease.deposit_amount',
            'lease.service_charge',
        ] as $key) {
            $this->assertArrayHasKey($key, ReportBuilderService::ALLOWED_FIELDS);
        }
    }

    // -- behaviour smoke ---------------------------------------------------

    public function test_report_share_is_active_contract(): void
    {
        $share = new ReportShare(['expires_at' => now()->addDay(), 'revoked_at' => null]);
        $this->assertTrue($share->isActive());

        $revoked = new ReportShare(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->assertFalse($revoked->isActive());

        $expired = new ReportShare(['expires_at' => now()->subDay(), 'revoked_at' => null]);
        $this->assertFalse($expired->isActive());
    }

    public function test_runbook_documents_phase73(): void
    {
        $this->assertTrue(file_exists(base_path('docs/runbooks/reports.md')));
        $md = file_get_contents(base_path('docs/runbooks/reports.md'));
        $this->assertStringContainsString('REPORTS-DEPTH-2', $md);
    }
}
