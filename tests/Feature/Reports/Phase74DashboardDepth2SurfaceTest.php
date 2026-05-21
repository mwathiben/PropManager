<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Controllers\Reports\DashboardController;
use App\Http\Controllers\Reports\DashboardShareController;
use App\Models\DashboardShare;
use App\Services\Reports\Cards\ChartCardRenderer;
use App\Services\Reports\Cards\DashboardCardRenderer;
use App\Services\Reports\Cards\KpiCardRenderer;
use App\Services\Reports\Cards\MetricCardRenderer;
use App\Services\Reports\Cards\SavedReportCardRenderer;
use App\Services\Reports\Cards\TextCardRenderer;
use App\Services\Reports\DashboardCardRegistry;
use App\Services\Reports\DashboardPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-74 CI: consolidated DASHBOARD-DEPTH-2 surface watchdog — tables,
 * classes (registry + 5 renderers + share + pdf), routes, Vue tokens, and
 * lang parity. Fails fast if a later rename/removal regresses the surface.
 */
class Phase74DashboardDepth2SurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shares_table_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('dashboard_shares'));
        foreach (['landlord_id', 'landlord_dashboard_id', 'expires_at', 'revoked_at', 'last_viewed_at', 'view_count'] as $col) {
            $this->assertTrue(Schema::hasColumn('dashboard_shares', $col), "dashboard_shares.{$col} missing");
        }
    }

    public function test_card_registry_and_renderers_exist(): void
    {
        $this->assertTrue(class_exists(DashboardCardRegistry::class));
        $this->assertTrue(interface_exists(DashboardCardRenderer::class));
        foreach ([SavedReportCardRenderer::class, MetricCardRenderer::class, KpiCardRenderer::class, ChartCardRenderer::class, TextCardRenderer::class] as $renderer) {
            $this->assertTrue(class_exists($renderer), "{$renderer} missing");
        }

        $registry = app(DashboardCardRegistry::class);
        foreach (['saved_report', 'metric', 'kpi', 'chart', 'text'] as $type) {
            $this->assertTrue($registry->has($type), "registry missing {$type}");
        }
    }

    public function test_share_and_pdf_classes_exist(): void
    {
        $this->assertTrue(class_exists(DashboardShare::class));
        $this->assertTrue(class_exists(DashboardShareController::class));
        $this->assertTrue(class_exists(DashboardPdfService::class));
        $this->assertTrue(method_exists(DashboardController::class, 'exportPdf'));
        $this->assertTrue(method_exists(DashboardController::class, 'exportXlsx'));
    }

    public function test_routes_exist(): void
    {
        foreach ([
            'dashboards.shares.index',
            'dashboards.shares.store',
            'dashboards.shares.revoke',
            'dashboards.share.view',
            'dashboards.export-pdf',
            'dashboards.export-xlsx',
            'dashboard.scope.update',
            'reports.metrics.validate',
        ] as $name) {
            $this->assertTrue(Route::has($name), "route {$name} missing");
        }
    }

    public function test_vue_components_exist_with_tokens(): void
    {
        $cases = [
            'resources/js/Components/Hub/HubShell.vue' => null,
            'resources/js/Components/Dashboard/KpiCard.vue' => 'kpi-card',
            'resources/js/Components/Dashboard/ChartCard.vue' => 'chart-card',
            'resources/js/Components/Dashboard/TextCard.vue' => 'text-card',
            'resources/js/Pages/Dashboards/Shares.vue' => 'dashboard-share',
            'resources/js/Pages/Dashboards/Show.vue' => 'dashboard-export-pdf',
        ];
        foreach ($cases as $path => $token) {
            $full = base_path($path);
            $this->assertFileExists($full);
            if ($token !== null) {
                $this->assertStringContainsString("data-testid=\"{$token}\"", file_get_contents($full), "{$path} missing {$token}");
            }
        }
    }

    public function test_lang_blocks_have_matching_keys_across_locales(): void
    {
        $en = require base_path('lang/en/reports.php');
        $sw = require base_path('lang/sw/reports.php');
        $ar = require base_path('lang/ar/reports.php');

        foreach (['dashboard_share', 'dashboards'] as $block) {
            $this->assertArrayHasKey($block, $en);
            $this->assertSame(array_keys($en[$block]), array_keys($sw[$block] ?? []), "sw reports.{$block} key drift");
            $this->assertSame(array_keys($en[$block]), array_keys($ar[$block] ?? []), "ar reports.{$block} key drift");
        }

        foreach (['en', 'sw', 'ar'] as $locale) {
            $dash = require base_path("lang/{$locale}/dashboard.php");
            $this->assertArrayHasKey('scope', $dash);
            $this->assertArrayHasKey('all_buildings', $dash['scope']);
        }
    }

    public function test_buildpayload_is_the_single_render_path(): void
    {
        // The PDF + share both go through DashboardService::buildPayload — no
        // duplicate card-rendering logic in the export/share paths.
        $this->assertStringContainsString(
            'buildPayload',
            file_get_contents(base_path('app/Services/Reports/DashboardPdfService.php')),
        );
        $this->assertStringContainsString(
            'buildPayload',
            file_get_contents(base_path('app/Http/Controllers/Reports/DashboardShareController.php')),
        );
    }

    public function test_runbook_documents_phase74(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/reports.md'));
        $this->assertStringContainsString('DASHBOARD-DEPTH-2', $md);
    }
}
