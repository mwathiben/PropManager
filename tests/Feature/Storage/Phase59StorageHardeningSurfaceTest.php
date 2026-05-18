<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Console\Commands\FileAccessAnomalyAudit;
use App\Console\Commands\StorageEnforceRetention;
use App\Models\FileAccessAudit;
use App\Models\FileRetentionPolicy;
use App\Services\Storage\FileAccessRecorder;
use App\Services\Storage\FileRetentionService;
use App\Services\Storage\PrefixedDisk;
use App\Services\Storage\TempFileHandle;
use App\Services\Storage\TempFileResolver;
use App\Services\Storage\TenantDiskResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-59 STORAGE-HARDENING CI surface watchdog. Cross-category
 * presence map for every Phase 59 closure: signed-URL resolver,
 * local-stream route, TempFileResolver pattern, PrefixedDisk
 * decorator, file_retention_policies table + storage:enforce-retention
 * cron, file_access_audits table + 5-min anomaly cron, storage.md
 * Phase-59 section, alert-thresholds rows.
 */
class Phase59StorageHardeningSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- SIGNED-URLS -----------------------------------------------------

    public function test_tenant_disk_resolver_has_temporary_url_method(): void
    {
        $this->assertTrue(method_exists(TenantDiskResolver::class, 'temporaryUrl'));
    }

    public function test_files_local_stream_route_registered(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('files.local-stream');
        $this->assertNotNull($route, 'files.local-stream route is not registered.');
        $this->assertContains('signed', $route->gatherMiddleware());
    }

    // -- PATH-CAVEAT -----------------------------------------------------

    public function test_temp_file_resolver_and_handle_classes_exist(): void
    {
        $this->assertTrue(class_exists(TempFileResolver::class));
        $this->assertTrue(class_exists(TempFileHandle::class));
        $this->assertTrue(method_exists(TempFileResolver::class, 'for'));
        $this->assertTrue(method_exists(TempFileHandle::class, 'cleanup'));
        $this->assertTrue(method_exists(TempFileHandle::class, '__destruct'));
    }

    // -- TENANT-ROUTING --------------------------------------------------

    public function test_prefixed_disk_class_exists_with_path_methods(): void
    {
        $this->assertTrue(class_exists(PrefixedDisk::class));
        foreach (['path', 'exists', 'get', 'put', 'delete', 'files'] as $method) {
            $this->assertTrue(method_exists(PrefixedDisk::class, $method));
        }
    }

    public function test_tenant_disk_prefix_template_config_key_documented(): void
    {
        // Key exists in the config; value defaults to null env-driven.
        $this->assertArrayHasKey('tenant_disk_prefix_template', config('filesystems'));
    }

    // -- FILE-RETENTION --------------------------------------------------

    public function test_file_retention_policies_table_and_subjects(): void
    {
        $this->assertTrue(Schema::hasTable('file_retention_policies'));
        $this->assertCount(7, FileRetentionPolicy::SUBJECTS);
    }

    public function test_storage_enforce_retention_scheduled_at_0230(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'storage:enforce-retention'));
        $this->assertNotNull($entry);
        $this->assertSame('30 2 * * *', $entry->expression);

        $this->assertTrue(class_exists(StorageEnforceRetention::class));
        $this->assertTrue(class_exists(FileRetentionService::class));
    }

    // -- ACCESS-AUDIT ----------------------------------------------------

    public function test_file_access_audits_table_and_recorder(): void
    {
        $this->assertTrue(Schema::hasTable('file_access_audits'));
        $this->assertTrue(class_exists(FileAccessAudit::class));
        $this->assertTrue(class_exists(FileAccessRecorder::class));
    }

    public function test_anomaly_audit_command_scheduled_every_five_minutes(): void
    {
        $this->assertTrue(class_exists(FileAccessAnomalyAudit::class));

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'file-access:anomaly-audit'));
        $this->assertNotNull($entry);
        $this->assertSame('*/5 * * * *', $entry->expression);
    }

    // -- DOCS ------------------------------------------------------------

    public function test_storage_runbook_has_phase_59_section(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/storage.md'));
        $this->assertStringContainsString('Phase 59', $body);
        $this->assertStringContainsString('STORAGE-HARDENING', $body);
        $this->assertStringContainsString('temporaryUrl', $body);
        $this->assertStringContainsString('TempFileResolver', $body);
        $this->assertStringContainsString('tenant_disk_prefix_template', $body);
    }

    public function test_alert_thresholds_md_has_phase_59_rows(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('files_retention_purged_count', $body);
        $this->assertStringContainsString('file_access_anomaly_count', $body);
    }
}
