<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-75 MAINTENANCE-DEPTH-3 surface watchdog. Each category's closure is
 * locked into presence assertions a future refactor cannot silently regress.
 * Behavioural tests live in the category-named sibling files; this is the
 * cross-category presence map.
 */
class Phase75MaintenanceDepth3SurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- VENDOR-PERF -------------------------------------------------------

    public function test_vendor_performance_route_and_service_present(): void
    {
        $this->assertTrue(Route::has('maintenance.vendor-performance'));
        $this->assertTrue(class_exists(\App\Services\Vendors\VendorPerformanceService::class));
        $this->assertFileExists(base_path('resources/js/Pages/Maintenance/VendorPerformance.vue'));
    }

    // -- VENDOR-ROUTING ----------------------------------------------------

    public function test_vendor_specialties_present(): void
    {
        $this->assertTrue(Schema::hasTable('vendor_specialties'));
        $this->assertTrue(class_exists(\App\Models\VendorSpecialty::class));
        $this->assertTrue(method_exists(\App\Models\Vendor::class, 'syncSpecialties'));
    }

    public function test_vendor_routing_service_and_config_present(): void
    {
        $this->assertTrue(method_exists(\App\Services\Maintenance\VendorAssignmentService::class, 'suggestPool'));
        $this->assertTrue(method_exists(\App\Services\Maintenance\VendorAssignmentService::class, 'autoAssign'));
        $this->assertIsBool(config('maintenance.auto_route_vendors'));
        $this->assertIsInt(config('maintenance.default_lead_time_days'));
    }

    // -- PARTS-PRICING -----------------------------------------------------

    public function test_parts_pricing_schema_and_models_present(): void
    {
        $this->assertTrue(Schema::hasTable('part_price_history'));
        $this->assertTrue(Schema::hasTable('part_suppliers'));
        $this->assertTrue(class_exists(\App\Models\PartPriceHistory::class));
        $this->assertTrue(class_exists(\App\Models\PartSupplier::class));
        $this->assertTrue(class_exists(\App\Observers\PartObserver::class));
    }

    public function test_parts_pricing_routes_and_ui_present(): void
    {
        foreach (['parts.pricing', 'parts.suppliers.store', 'parts.suppliers.destroy'] as $name) {
            $this->assertTrue(Route::has($name), "Route {$name} missing");
        }
        $this->assertFileExists(base_path('resources/js/Pages/Parts/Pricing.vue'));

        foreach (['en', 'sw', 'ar'] as $locale) {
            $this->assertFileExists(base_path("lang/{$locale}/parts.php"));
        }
    }

    // -- PARTS-PREDICT -----------------------------------------------------

    public function test_parts_predict_service_and_columns_present(): void
    {
        $this->assertTrue(class_exists(\App\Services\Maintenance\PartUsageService::class));
        $this->assertTrue(method_exists(\App\Services\Maintenance\PartUsageService::class, 'dailyRate'));
        $this->assertTrue(Schema::hasColumn('draft_purchase_order_lines', 'trigger_reason'));
        $this->assertTrue(Schema::hasColumn('draft_purchase_order_lines', 'projected_stockout_at'));
    }

    public function test_parts_predict_gauges_emitted_by_crons(): void
    {
        $reorder = (string) file_get_contents(base_path('app/Console/Commands/PartsReorderSuggest.php'));
        $this->assertStringContainsString('parts_predicted_stockout_count', $reorder);

        $audit = (string) file_get_contents(base_path('app/Console/Commands/PartsAuditStock.php'));
        $this->assertStringContainsString('parts_usage_rate_per_day', $audit);
    }

    // -- PHOTO-ROLLUP ------------------------------------------------------

    public function test_photo_gallery_routes_controller_and_ui_present(): void
    {
        $this->assertTrue(Route::has('maintenance.photos'));
        $this->assertTrue(Route::has('maintenance.photos.export-pdf'));
        $this->assertTrue(class_exists(\App\Http\Controllers\MaintenancePhotoGalleryController::class));
        $this->assertFileExists(base_path('resources/js/Pages/Maintenance/PhotoGallery.vue'));
        $this->assertFileExists(base_path('resources/views/pdf/maintenance-photos.blade.php'));
    }

    public function test_photo_export_pdf_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('maintenance.photos.export-pdf');
        $this->assertNotNull($route);
        $this->assertContains('throttle:pdf-render', $route->gatherMiddleware());
    }

    // -- CI ----------------------------------------------------------------

    public function test_maintenance_runbook_mentions_phase_75(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/maintenance.md'));
        $this->assertStringContainsString('Phase 75', $body);
        $this->assertStringContainsString('MAINTENANCE-DEPTH-3', $body);
    }

    public function test_maintenance_photos_lang_present_in_all_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('maintenance.photos.title', __('maintenance.photos.title'));
        }
    }
}
