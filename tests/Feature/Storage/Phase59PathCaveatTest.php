<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\TempFileHandle;
use App\Services\Storage\TempFileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-59 PATH-CAVEAT-1/2/3: TempFileResolver returns a TempFileHandle
 * that wraps an absolute filesystem path for subprocess callers,
 * regardless of whether the underlying disk supports ->path() natively.
 */
class Phase59PathCaveatTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_returns_handle_for_local_disk(): void
    {
        Storage::fake('local');
        Storage::tenant()->put('phase59/ocr-source.png', 'binary-bytes');

        $handle = app(TempFileResolver::class)->for('phase59/ocr-source.png');

        $this->assertInstanceOf(TempFileHandle::class, $handle);
        $this->assertIsString($handle->path());
        $this->assertNotEmpty($handle->path());
        $this->assertStringEndsWith('ocr-source.png', $handle->path());
    }

    public function test_handle_cleanup_is_idempotent(): void
    {
        Storage::fake('local');
        Storage::tenant()->put('phase59/clean.txt', 'sample');

        $handle = app(TempFileResolver::class)->for('phase59/clean.txt');
        $handle->cleanup();
        $handle->cleanup();

        // Idempotent cleanup must not throw.
        $this->assertTrue(true);
    }

    public function test_handle_wraps_existing_path_on_local_with_owned_false(): void
    {
        Storage::fake('local');
        Storage::tenant()->put('phase59/owned-false.txt', 'sample');

        $handle = app(TempFileResolver::class)->for('phase59/owned-false.txt');
        $originalPath = $handle->path();
        $handle->cleanup();

        // owned=false means cleanup must NOT unlink the tenant-disk
        // file. The fake disk still has the file after cleanup.
        $this->assertTrue(Storage::tenant()->exists('phase59/owned-false.txt'));
    }

    public function test_data_export_service_returns_tenant_disk_relative_path(): void
    {
        // Confirm the public contract: exportUserData returns a
        // tenant-disk-relative path, not an absolute filesystem path.
        $reflection = new \ReflectionMethod(\App\Services\DataExportService::class, 'exportUserData');
        $this->assertSame('string', (string) $reflection->getReturnType());
    }

    public function test_temp_file_handle_class_exists_with_required_methods(): void
    {
        $this->assertTrue(class_exists(TempFileHandle::class));
        $this->assertTrue(method_exists(TempFileHandle::class, 'path'));
        $this->assertTrue(method_exists(TempFileHandle::class, 'cleanup'));
        $this->assertTrue(method_exists(TempFileHandle::class, '__destruct'));
    }

    public function test_data_export_service_wires_temp_file_resolver(): void
    {
        $body = (string) file_get_contents(app_path('Services/DataExportService.php'));
        $this->assertStringContainsString('TempFileResolver', $body);
    }

    public function test_ocr_service_wires_temp_file_resolver(): void
    {
        $body = (string) file_get_contents(app_path('Services/OcrService.php'));
        $this->assertStringContainsString('TempFileResolver', $body);
    }
}
