<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Building;
use App\Models\Document;
use App\Models\Notification;
use App\Services\Water\WaterComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-92 WATER-COMPLIANCE surface guard: schema, document types, Building
 * documents relationship, routes, notification reuse, service shape, lang parity.
 */
class Phase92WaterComplianceSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('buildings', 'water_abstraction_limit'));
    }

    public function test_document_types_added(): void
    {
        $this->assertArrayHasKey('wra_abstraction_permit', Document::DOCUMENT_TYPES);
        $this->assertArrayHasKey('water_quality_certificate', Document::DOCUMENT_TYPES);
    }

    public function test_building_has_documents_relationship(): void
    {
        $this->assertTrue(method_exists(Building::class, 'documents'));
    }

    public function test_routes_registered(): void
    {
        $this->assertTrue(Route::has('water.compliance.limit'));
        // Compliance reuses the existing document upload/renew routes.
        $this->assertTrue(Route::has('documents.store'));
        $this->assertTrue(Route::has('documents.renew'));
    }

    public function test_reuses_document_expiry_notification(): void
    {
        // No NEW notification type — compliance reuses the Phase-82 document_expiry.
        $this->assertArrayHasKey(Notification::TYPE_DOCUMENT_EXPIRY, Notification::TYPE_URGENCY_MAP);
    }

    public function test_service_resolves_and_returns_shape(): void
    {
        $out = app(WaterComplianceService::class)->forLandlord(999999);
        $this->assertArrayHasKey('buildings', $out);
        $this->assertArrayHasKey('summary', $out);
        $this->assertSame([], $out['buildings']);
        $this->assertArrayHasKey('borehole_buildings', $out['summary']);
    }

    public function test_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $doc = require base_path("lang/{$locale}/document.php");
            $this->assertArrayHasKey('compliance', $water['tabs'] ?? [], "{$locale} missing water.tabs.compliance");
            $compliance = $water['compliance'] ?? [];
            $this->assertArrayHasKey('permit', $compliance, "{$locale} missing water.compliance.permit");
            $this->assertArrayHasKey('status', $compliance, "{$locale} missing water.compliance.status");
            $this->assertArrayHasKey('overall', $compliance, "{$locale} missing water.compliance.overall");
            $this->assertArrayHasKey('wra_abstraction_permit', $doc['types'] ?? [], "{$locale} missing document.types.wra_abstraction_permit");
            $this->assertArrayHasKey('water_quality_certificate', $doc['types'] ?? [], "{$locale} missing document.types.water_quality_certificate");
        }
    }
}
