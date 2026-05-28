<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-55 DASHBOARD-DEPTH surface watchdog. Locks each category's closure
 * into a single assertion that a future refactor cannot silently regress.
 * Per-category behavioural tests live in the category-named sibling files;
 * this class is the cross-category presence map.
 */
class Phase55DashboardDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- RECENT-PAYMENTS ---------------------------------------------------

    public function test_recent_payments_query_has_payment_date_order_with_trashed_and_voided_filter(): void
    {
        $body = (string) file_get_contents(base_path('app/Services/DashboardService.php'));

        // RECENT-PAYMENTS-2: Lease lookup must use withTrashed.
        $this->assertStringContainsString(
            "Lease::withTrashed()->whereIn('unit_id', \$metricsUnitIds)->pluck('id')",
            $body,
            'RECENT-PAYMENTS-2: withTrashed() missing from the metrics lease-id lookup.',
        );

        // RECENT-PAYMENTS-1 + 3: recent-payments query orders by payment_date desc
        // and filters is_voided = false.
        $this->assertStringContainsString(
            "->where('is_voided', false)",
            $body,
            'RECENT-PAYMENTS-3: is_voided=false filter missing from recent-payments query.',
        );
        $this->assertStringContainsString(
            "->orderBy('payment_date', 'desc')",
            $body,
            'RECENT-PAYMENTS-1: orderBy(payment_date) missing from recent-payments query.',
        );
    }

    // -- PAYMENT-DETAIL ----------------------------------------------------

    public function test_payment_detail_route_registered(): void
    {
        $this->assertTrue(Route::has('payments.detail.show'));
    }

    public function test_payment_detail_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\PaymentDetailController::class));
        $this->assertTrue(method_exists(\App\Http\Controllers\PaymentDetailController::class, 'show'));
    }

    public function test_payment_detail_vue_page_exists(): void
    {
        $this->assertFileExists(base_path('resources/js/Pages/Payments/Detail.vue'));
    }

    // -- DASHBOARD-FILTERS -------------------------------------------------

    public function test_dashboard_service_supports_all_buildings_sentinel(): void
    {
        $body = (string) file_get_contents(base_path('app/Services/DashboardService.php'));
        $this->assertStringContainsString("\$buildingId === 'all'", $body);
        $this->assertStringContainsString('allBuildingsMode', $body);
        $this->assertStringContainsString('getCrossBuildingMetricsContext', $body);
    }

    public function test_dashboard_vue_renders_building_filter_chip(): void
    {
        $body = (string) file_get_contents(base_path('resources/js/Pages/Dashboard.vue'));
        $this->assertStringContainsString('data-testid="dashboard-building-chip"', $body);
        // Phase-24+ I18N migration: the "All buildings" label is now
        // resolved via the i18n key. Asserting the key reference rather
        // than the hardcoded string keeps the test future-proof against
        // translation file edits.
        $this->assertStringContainsString("t('dashboard.building_chip.all_buildings')", $body);
    }

    // -- LEASE-STATE-BADGE -------------------------------------------------

    public function test_dashboard_vue_renders_lease_state_badge(): void
    {
        $body = (string) file_get_contents(base_path('resources/js/Pages/Dashboard.vue'));
        $this->assertStringContainsString('data-testid="lease-state-badge"', $body);
        $this->assertStringContainsString('leaseStateBadgeClass', $body);
    }

    // -- WIDGET-ORDERING ---------------------------------------------------

    public function test_dashboard_preferences_route_registered(): void
    {
        $this->assertTrue(Route::has('dashboards.preferences.update'));
    }

    public function test_dashboard_preference_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\DashboardPreferenceController::class));
        $this->assertSame(
            'main_dashboard',
            \App\Http\Controllers\DashboardPreferenceController::MAIN_DASHBOARD_SLUG,
        );
        $this->assertNotEmpty(\App\Http\Controllers\DashboardPreferenceController::ALLOWED_WIDGETS);
    }

    public function test_dashboard_vue_has_drag_handlers_on_bottom_row_cards(): void
    {
        $body = (string) file_get_contents(base_path('resources/js/Pages/Dashboard.vue'));
        $this->assertStringContainsString('onWidgetDragStart', $body);
        $this->assertStringContainsString('onWidgetDrop', $body);
        foreach (['widget-recent-payments', 'widget-recent-tickets', 'widget-expiring-leases'] as $testId) {
            $this->assertStringContainsString("data-testid=\"{$testId}\"", $body);
        }
    }

    // -- CI ----------------------------------------------------------------

    public function test_dashboard_runbook_mentions_phase_55(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/dashboard.md'));
        $this->assertStringContainsString('Phase 55', $body);
        $this->assertStringContainsString('DASHBOARD-DEPTH', $body);
    }

    public function test_phase49_prd_audit_closeout_unchanged(): void
    {
        // Phase 55 does NOT modify Phase 49 PRD; this is a sanity guard so a
        // future change that touches phase-49-audit-prd.json must justify it.
        $this->assertFileExists(base_path('phase-49-audit-prd.json'));
        $body = (string) file_get_contents(base_path('phase-49-audit-prd.json'));
        $this->assertStringContainsString('MAINTENANCE-DEPTH', $body);
    }
}
