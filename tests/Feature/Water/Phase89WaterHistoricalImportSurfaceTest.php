<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Services\ImportService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-89 WATER-HISTORICAL-IMPORT surface guard: Excel parser, template, route,
 * and i18n parity.
 */
class Phase89WaterHistoricalImportSurfaceTest extends TestCase
{
    public function test_spreadsheet_parser_exists(): void
    {
        $this->assertTrue(method_exists(ImportService::class, 'parseSpreadsheet'));
        $this->assertTrue(method_exists(ImportService::class, 'parseRows'));
    }

    public function test_water_template_has_consumption_and_cost_columns(): void
    {
        $template = ImportService::getTemplate('water_readings');
        $this->assertContains('Consumption', $template['headers']);
        $this->assertContains('Cost', $template['headers']);
    }

    public function test_imports_route_is_registered(): void
    {
        $this->assertTrue(Route::has('imports.index'));
        $this->assertTrue(Route::has('imports.upload'));
    }

    public function test_import_history_lang_parity(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $water = require base_path("lang/{$locale}/water.php");
            $this->assertArrayHasKey('import_history', $water, "{$locale} missing water.import_history");
        }
    }

    public function test_runbook_documents_import(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/water.md'));
        $this->assertStringContainsStringIgnoringCase('import', $runbook);
    }
}
