<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\TenantDiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-58 SHARED-DISK-MIGRATION CI surface watchdog. Cross-category
 * presence map covering every Phase 58 closure: resolver service, macro
 * registration, config knob, refactored callsites, zero remaining
 * Storage::disk('local') literals, and the storage runbook.
 */
class Phase58SharedDiskMigrationSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private const REFACTORED_FILES = [
        'app/Jobs/GenerateInvoicePdf.php',
        'app/Models/Document.php',
        'app/Models/WaterReading.php',
        'app/Services/DataExportService.php',
        'app/Services/ImportService.php',
        'app/Services/InvoicePdfService.php',
        'app/Services/OcrService.php',
        'app/Services/Tickets/TicketAnnotationService.php',
        'app/Http/Controllers/DocumentController.php',
        'app/Http/Controllers/LeaseController.php',
        'app/Http/Controllers/TenantDocumentsController.php',
        'app/Http/Controllers/TenantKycController.php',
        'app/Http/Controllers/WaterReadingController.php',
    ];

    // -- TENANT-DISK-RESOLVER --------------------------------------------

    public function test_tenant_disk_resolver_class_and_method(): void
    {
        $this->assertTrue(class_exists(TenantDiskResolver::class));
        $this->assertTrue(method_exists(TenantDiskResolver::class, 'resolve'));
    }

    public function test_storage_tenant_macro_returns_filesystem(): void
    {
        $disk = Storage::tenant();
        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_storage_tenant_macro_accepts_landlord_id(): void
    {
        $disk = Storage::tenant(landlordId: 42);
        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_tenant_disk_config_knob_is_registered(): void
    {
        // The knob must be configured (defaults to 'local' via env fallback).
        $configured = config('filesystems.tenant_disk');
        $this->assertNotEmpty($configured, 'filesystems.tenant_disk must be configured.');
        $this->assertIsString($configured);
    }

    // -- CALLSITE-REFACTOR -----------------------------------------------

    public function test_every_refactored_file_uses_storage_tenant(): void
    {
        foreach (self::REFACTORED_FILES as $relativePath) {
            $absolutePath = base_path($relativePath);
            $this->assertFileExists($absolutePath, "Refactored file missing: {$relativePath}");
            $contents = (string) file_get_contents($absolutePath);
            $this->assertStringContainsString(
                'Storage::tenant(',
                $contents,
                "Phase-58 refactor regression: {$relativePath} no longer uses Storage::tenant().",
            );
        }
    }

    public function test_zero_storage_disk_local_callsites_remain_in_app(): void
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $count += substr_count((string) file_get_contents($file->getPathname()), "Storage::disk('local')");
        }

        $this->assertSame(
            0,
            $count,
            'Phase-58 regression: a Storage::disk(\'local\') callsite was reintroduced. Use Storage::tenant() instead.',
        );
    }

    // -- DOCS ------------------------------------------------------------

    public function test_storage_runbook_exists_and_mentions_phase_58(): void
    {
        $path = base_path('docs/runbooks/storage.md');
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('Phase 58', $body);
        $this->assertStringContainsString('SHARED-DISK-MIGRATION', $body);
        $this->assertStringContainsString('Storage::tenant', $body);
    }

    public function test_autoscale_runbook_marks_perf_scale_3_closed(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/autoscale-readiness.md'));
        $this->assertStringContainsString('PERF-SCALE-3', $body);
        $this->assertStringContainsString('CLOSED Phase 58', $body);
    }

    // -- LINEAGE ---------------------------------------------------------

    public function test_phase_22_baseline_is_zero(): void
    {
        $body = (string) file_get_contents(
            base_path('tests/Feature/Performance/Phase22StatelessnessTest.php'),
        );
        $this->assertStringContainsString(
            'LOCAL_DISK_CALLSITE_BASELINE = 0',
            $body,
            'Phase-22 baseline should have dropped to 0 after Phase 58 refactor.',
        );
    }
}
