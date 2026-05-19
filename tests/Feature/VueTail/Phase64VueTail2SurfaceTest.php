<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-64 CI-1: cross-category surface map. Locks in every Vue
 * mount + service class + route + table that Phase 64 ships. Catches
 * refactor drift before it surfaces in production.
 */
class Phase64VueTail2SurfaceTest extends TestCase
{
    public function test_inbox_mount_vue_artifacts_present(): void
    {
        foreach ([
            'resources/js/Components/InboxBell.vue',
            'resources/js/Components/Inbox/InitiateThreadDialog.vue',
            'resources/js/Components/Inbox/VirtualMessageList.vue',
            'resources/js/Components/Inbox/AttachmentPreviewList.vue',
            'resources/js/composables/useFileDropZone.ts',
        ] as $rel) {
            $this->assertFileExists(base_path($rel), "missing: {$rel}");
        }
    }

    public function test_offline_mount_artifacts_present(): void
    {
        foreach ([
            'resources/js/lib/writeConflictBus.ts',
            'resources/js/lib/pwaTelemetry.ts',
        ] as $rel) {
            $this->assertFileExists(base_path($rel), "missing: {$rel}");
        }
    }

    public function test_authenticated_layout_mounts_all_phase64_components(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString('InboxBell', $contents);
        $this->assertStringContainsString('ConflictDialog', $contents);
        $this->assertStringContainsString('writeConflictBus', $contents);
        $this->assertStringContainsString("route('message-threads.index')", $contents);
        $this->assertStringContainsString("route('tenant.inbox.index')", $contents);
    }

    public function test_legal_hold_class_and_table_present(): void
    {
        $this->assertTrue(class_exists(\App\Models\LegalHold::class));
        $this->assertTrue(class_exists(\App\Support\LegalHoldRegistry::class));
        $this->assertTrue(class_exists(\App\Policies\LegalHoldPolicy::class));
        $this->assertTrue(Schema::hasTable('legal_holds'));
    }

    public function test_pwa_telemetry_endpoint_registered(): void
    {
        $route = collect(\Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'api.v1.telemetry.pwa');

        $this->assertNotNull($route, 'api.v1.telemetry.pwa route must be registered.');
        $this->assertContains('POST', $route->methods());
        $this->assertContains('throttle:telemetry', $route->gatherMiddleware());
    }

    public function test_messages_destroy_route_still_registered_after_phase64_edits(): void
    {
        $names = collect(\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->all();

        // Phase 63 routes must still exist + remain reachable through
        // Phase 64 edits (regression sentinel).
        foreach (['messages.read', 'messages.destroy', 'message-threads.archive', 'tenant.inbox.store'] as $name) {
            $this->assertContains($name, $names, "Phase 63 route {$name} regressed.");
        }
    }

    public function test_messages_enforce_retention_uses_legal_hold_registry(): void
    {
        $contents = file_get_contents(base_path('app/Console/Commands/MessagesEnforceRetention.php'));

        $this->assertStringContainsString('LegalHoldRegistry', $contents);
        $this->assertStringContainsString('heldIdsFor', $contents);
        $this->assertStringContainsString('messages_legal_hold_count', $contents);
    }

    public function test_ticket_and_water_reading_controllers_call_assert_if_match(): void
    {
        $ticket = file_get_contents(base_path('app/Http/Controllers/TicketController.php'));
        $water = file_get_contents(base_path('app/Http/Controllers/WaterReadingController.php'));

        $this->assertStringContainsString('$ticket->assertIfMatch(', $ticket);
        $this->assertStringContainsString('$reading->assertIfMatch(', $water);
    }

    public function test_alert_thresholds_documents_telemetry_endpoint(): void
    {
        $contents = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));

        $this->assertStringContainsString('Phase-64 TELEMETRY-WIRE', $contents);
        $this->assertStringContainsString('/api/v1/telemetry/pwa', $contents);
    }

    public function test_pending_sync_badge_present_on_four_show_pages(): void
    {
        foreach ([
            'resources/js/Pages/Invoices/Show.vue',
            'resources/js/Pages/Tickets/Show.vue',
            'resources/js/Pages/MessageThreads/Show.vue',
            'resources/js/Pages/Tenant/Inbox/Show.vue',
        ] as $rel) {
            $contents = file_get_contents(base_path($rel));
            $this->assertStringContainsString(
                'PendingSyncBadge',
                $contents,
                "{$rel} must mount PendingSyncBadge",
            );
        }
    }

    public function test_route_table_carries_phase64_additions(): void
    {
        $names = collect(\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->all();

        $this->assertContains('api.v1.telemetry.pwa', $names);
    }
}
