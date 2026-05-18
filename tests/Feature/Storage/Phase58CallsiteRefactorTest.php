<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use PHPUnit\Framework\TestCase;

/**
 * Phase-58 INTEGRATION-TESTS-2 + DROP-BASELINE-3 watchdog. Asserts that:
 *
 * 1. The 13 refactored files each contain Storage::tenant() — proves the
 *    migration happened in those files.
 * 2. Zero files in app/ contain the literal Storage::disk('local') —
 *    PR-blocker against future re-introduction.
 *
 * Plain PHPUnit TestCase (no Laravel bootstrap) because both assertions
 * are pure file_get_contents reads.
 */
class Phase58CallsiteRefactorTest extends TestCase
{
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

    public function test_every_refactored_file_uses_storage_tenant(): void
    {
        $base = dirname(__DIR__, 3);
        foreach (self::REFACTORED_FILES as $relativePath) {
            $absolutePath = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $this->assertFileExists($absolutePath);
            $contents = (string) file_get_contents($absolutePath);
            $this->assertStringContainsString(
                'Storage::tenant(',
                $contents,
                "Phase-58 refactor regression: {$relativePath} no longer uses Storage::tenant().",
            );
        }
    }

    public function test_no_file_in_app_uses_storage_disk_local_literal(): void
    {
        $base = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'app';
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            $count += substr_count($contents, "Storage::disk('local')");
        }

        $this->assertSame(
            0,
            $count,
            'Phase-58 regression: a new Storage::disk(\'local\') callsite was added. Refactor to Storage::tenant() instead.',
        );
    }
}
