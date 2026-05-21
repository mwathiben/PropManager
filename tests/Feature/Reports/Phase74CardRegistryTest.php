<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\Cards\DashboardCardRenderer;
use App\Services\Reports\DashboardCardRegistry;
use App\Services\Reports\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-74 CARD-REGISTRY: the dashboard card-renderer registry resolves the
 * built-in types, rejects unknown types fail-closed, and feeds the editor its
 * card-type descriptors.
 */
class Phase74CardRegistryTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_registry_resolves_the_builtin_renderers(): void
    {
        $registry = app(DashboardCardRegistry::class);

        $this->assertTrue($registry->has('saved_report'));
        $this->assertTrue($registry->has('metric'));
        $this->assertInstanceOf(DashboardCardRenderer::class, $registry->get(0, 'saved_report'));
        $this->assertInstanceOf(DashboardCardRenderer::class, $registry->get(0, 'metric'));
    }

    public function test_unknown_card_type_is_rejected_fail_closed(): void
    {
        $this->expectException(ValidationException::class);

        app(DashboardService::class)->validateLayout([
            ['type' => 'bogus', 'saved_report_id' => 1],
        ], 1);
    }

    public function test_card_type_is_required(): void
    {
        $this->expectException(ValidationException::class);

        app(DashboardService::class)->validateLayout([
            ['saved_report_id' => 1],
        ], 1);
    }

    public function test_descriptors_expose_the_card_types_to_the_editor(): void
    {
        $descriptors = app(DashboardCardRegistry::class)->descriptors();
        $keys = array_column($descriptors, 'key');

        foreach (['saved_report', 'metric', 'kpi', 'chart', 'text'] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach ($descriptors as $d) {
            $this->assertArrayHasKey('label', $d);
            $this->assertArrayHasKey('needs_saved_report', $d);
            $this->assertArrayHasKey('needs_metric', $d);
        }
    }

    public function test_editor_create_payload_includes_card_types(): void
    {
        $landlord = $this->createLandlordWithFullSetup()['landlord'];

        $this->actingAs($landlord)
            ->get(route('dashboards.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Dashboards/Editor')
                ->has('cardTypes', 5));
    }
}
