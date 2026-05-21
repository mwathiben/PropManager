<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Notification;
use App\Services\Documents\DocumentGenerationService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-82 CI: consolidated DOCUMENTS-DEPTH surface watchdog.
 */
class Phase82DocumentsDepthSurfaceTest extends TestCase
{
    public function test_lifecycle_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('documents', [
            'issue_date', 'superseded_by_document_id', 'reminder_days', 'is_renewable',
        ]));
    }

    public function test_generation_service_bound(): void
    {
        $this->assertInstanceOf(DocumentGenerationService::class, app(DocumentGenerationService::class));
    }

    public function test_routes_registered(): void
    {
        foreach (['documents.renew', 'documents.generate-notice'] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_commands_exit_zero(): void
    {
        $this->artisan('documents:scan-expiring')->assertExitCode(0);
        $this->artisan('documents:expiry-rollup')->assertExitCode(0);
    }

    public function test_document_expiry_notification_type_mapped(): void
    {
        $this->assertArrayHasKey(Notification::TYPE_DOCUMENT_EXPIRY, Notification::TYPE_URGENCY_MAP);
    }

    public function test_document_expiry_preference_and_enum_wired(): void
    {
        // send() resolves a channel only when the per-type opt-in column exists,
        // and persists only when the type enum admits the value.
        $this->assertTrue(
            Schema::hasColumn('notification_preferences', 'document_expiry_enabled'),
            'notification_preferences.document_expiry_enabled missing — reminders cannot resolve a channel',
        );

        if (config('database.default') !== 'sqlite') {
            $type = \Illuminate\Support\Facades\DB::selectOne(
                'SHOW COLUMNS FROM notifications WHERE Field = ?', ['type'],
            )->Type;
            $this->assertStringContainsString(
                "'document_expiry'", $type,
                'notifications.type enum missing document_expiry — reminders cannot persist',
            );
        }
    }

    public function test_notice_blade_exists(): void
    {
        $this->assertFileExists(resource_path('views/documents/notice.blade.php'));
    }

    public function test_document_lang_parity(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($a as $k => $v) {
                $keys = is_array($v) ? [...$keys, ...$flatten($v, "{$prefix}{$k}.")] : [...$keys, "{$prefix}{$k}"];
            }

            return $keys;
        };

        $en = $flatten(require base_path('lang/en/document.php'));
        $sw = $flatten(require base_path('lang/sw/document.php'));
        $ar = $flatten(require base_path('lang/ar/document.php'));
        sort($en);
        sort($sw);
        sort($ar);

        $this->assertSame($en, $sw, 'sw/document.php key drift');
        $this->assertSame($en, $ar, 'ar/document.php key drift');
    }

    public function test_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/documents.md'));
    }
}
