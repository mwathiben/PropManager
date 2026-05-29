<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Support\LegalHoldRegistry;
use Tests\TestCase;

/**
 * Phase-65 CI-1: cross-category surface map. Locks every Vue + service
 * + controller + route + table + trait + cron Phase 65 ships. Catches
 * refactor drift before it surfaces in production.
 */
class Phase65LegalHoldExpandSurfaceTest extends TestCase
{
    public function test_allowed_holdable_types_lists_exactly_four_subjects(): void
    {
        $this->assertCount(4, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES);
        $this->assertContains(MessageThread::class, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES);
        $this->assertContains(Document::class, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES);
        $this->assertContains(Invoice::class, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES);
        $this->assertContains(Ticket::class, LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES);
    }

    public function test_morph_target_models_use_has_legal_holds_trait(): void
    {
        foreach ([Document::class, Invoice::class, Ticket::class, MessageThread::class] as $model) {
            $this->assertContains(
                \App\Models\Concerns\HasLegalHolds::class,
                array_keys((new \ReflectionClass($model))->getTraits()),
                "{$model} must use HasLegalHolds trait",
            );
        }
    }

    public function test_ticket_uses_auditable_trait(): void
    {
        $this->assertContains(
            \App\Traits\Auditable::class,
            array_keys((new \ReflectionClass(Ticket::class))->getTraits()),
            'Ticket must use Auditable trait (Phase 65 adds it for DPA audit metadata)',
        );
    }

    public function test_phase65_controllers_present(): void
    {
        foreach ([
            \App\Http\Controllers\LegalHoldController::class,
            \App\Http\Controllers\LegalHoldBulkController::class,
            \App\Http\Controllers\LegalHoldAuditExportController::class,
            \App\Http\Controllers\TenantLegalHoldController::class,
        ] as $controller) {
            $this->assertTrue(class_exists($controller), "{$controller} must exist");
        }
    }

    public function test_phase65_services_present(): void
    {
        $this->assertTrue(class_exists(\App\Services\Legal\BulkHoldService::class));
        $this->assertTrue(class_exists(\App\Services\Legal\LegalHoldAuditExportService::class));
    }

    public function test_phase65_routes_registered(): void
    {
        $names = collect(\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->all();

        foreach ([
            'legal-holds.index',
            'legal-holds.store',
            'legal-holds.destroy',
            'legal-holds.bulk.store',
            'legal-holds.bulk.destroy',
            'legal-holds.audit-export',
            'tenants.legal-hold',
        ] as $name) {
            $this->assertContains($name, $names, "Route {$name} must be registered");
        }
    }

    public function test_phase65_vue_files_present(): void
    {
        foreach ([
            'resources/js/Pages/LegalHolds/Index.vue',
            'resources/js/Pages/LegalHolds/AuditExport.vue',
            'resources/js/Components/LegalHold/HoldCreateModal.vue',
        ] as $rel) {
            $this->assertFileExists(base_path($rel), "missing: {$rel}");
        }
    }

    public function test_authenticated_layout_mounts_legal_holds_nav(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString('nav.legal_holds', $contents);
        $this->assertStringContainsString("route('legal-holds.index')", $contents);
        $this->assertStringContainsString('ScaleIcon', $contents);
    }

    public function test_nav_legal_holds_key_present_in_three_locales(): void
    {
        foreach (['en', 'sw', 'ar'] as $locale) {
            $contents = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);
            $this->assertArrayHasKey('legal_holds', $contents['nav'] ?? [],
                "lang/{$locale}.json must define nav.legal_holds");
        }
    }

    public function test_audit_legal_hold_exclusions_command_registered(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $matched = collect($events)->first(fn ($e) => is_string($e->command) && str_contains($e->command, 'legal-hold:audit-exclusions'),
        );
        $this->assertNotNull($matched, 'legal-hold:audit-exclusions cron must be scheduled');
    }

    public function test_file_retention_service_references_legal_hold_registry(): void
    {
        $contents = file_get_contents(base_path('app/Services/Storage/FileRetentionService.php'));
        $this->assertStringContainsString('LegalHoldRegistry::heldIdsFor', $contents);
        $this->assertStringContainsString('files_retention_held_count', $contents);
    }

    public function test_data_export_service_includes_legal_holds_stanza(): void
    {
        $contents = file_get_contents(base_path('app/Services/DataExportService.php'));
        $this->assertStringContainsString('legal_holds_blocking_erasure', $contents);
        $this->assertStringContainsString('17(3)(b)', $contents);
    }

    public function test_legal_hold_runbook_exists(): void
    {
        $this->assertFileExists(base_path('docs/runbooks/legal-hold.md'));
    }

    public function test_alert_thresholds_documents_phase65_gauges(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));

        foreach ([
            'legal_holds_active_count',
            'tenant_litigation_hold_subjects_count',
            'retention_legal_hold_exclusions_count',
            'files_retention_held_count',
            'files_retention_orphan_count',
        ] as $gauge) {
            $this->assertStringContainsString($gauge, $contents,
                "alert-thresholds.md must document {$gauge}");
        }
    }

    public function test_hold_create_modal_mounted_on_subject_show_pages(): void
    {
        foreach ([
            'resources/js/Pages/Invoices/Show.vue',
            'resources/js/Pages/Tickets/Show.vue',
        ] as $page) {
            $contents = file_get_contents(base_path($page));
            $this->assertStringContainsString('HoldCreateModal', $contents,
                "{$page} must import + mount HoldCreateModal (not orphaned)");
            $this->assertStringContainsString('subject-type=', $contents,
                "{$page} must pass subject-type prop to HoldCreateModal");
        }
    }

    public function test_legal_hold_phase64_regression_classes_still_present(): void
    {
        // Phase 64 watchdogs must keep passing after Phase 65 edits.
        $this->assertTrue(class_exists(\App\Support\LegalHoldRegistry::class));
        $this->assertTrue(class_exists(\App\Policies\LegalHoldPolicy::class));
        $this->assertTrue(class_exists(\App\Models\LegalHold::class));
    }
}
