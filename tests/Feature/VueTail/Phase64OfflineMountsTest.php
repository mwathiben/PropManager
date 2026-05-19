<?php

declare(strict_types=1);

namespace Tests\Feature\VueTail;

use App\Exceptions\WriteConflictException;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-64 OFFLINE-MOUNTS-1/2/3 watchdog: ConflictDialog global mount
 * via writeConflictBus, PendingSyncBadge per-page wiring, If-Match
 * controller adoption.
 */
class Phase64OfflineMountsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_write_conflict_bus_exists_with_expected_api(): void
    {
        $path = base_path('resources/js/lib/writeConflictBus.ts');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);
        foreach (['export function on', 'export function emit', 'export function clear', 'WriteConflictPayload'] as $token) {
            $this->assertStringContainsString(
                $token,
                $contents,
                "writeConflictBus.ts missing expected token '{$token}'",
            );
        }
    }

    public function test_authenticated_layout_mounts_conflict_dialog_via_bus(): void
    {
        $contents = file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.vue'));

        $this->assertStringContainsString(
            "import ConflictDialog from '@/Components/Offline/ConflictDialog.vue'",
            $contents,
        );
        $this->assertStringContainsString(
            "from '@/lib/writeConflictBus'",
            $contents,
        );
        $this->assertStringContainsString('<ConflictDialog', $contents);
        $this->assertStringContainsString('onWriteConflict', $contents);
    }

    public function test_sw_emits_write_conflict_message_on_409_replay(): void
    {
        $contents = file_get_contents(base_path('resources/js/sw.ts'));

        $this->assertStringContainsString("type: 'WRITE_CONFLICT_409'", $contents);
        $this->assertStringContainsString('response.status === 409', $contents);
    }

    public function test_app_forwards_write_conflict_message_to_bus(): void
    {
        $contents = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("data.type === 'WRITE_CONFLICT_409'", $contents);
        $this->assertStringContainsString("'@/lib/writeConflictBus'", $contents);
    }

    public function test_pending_sync_badge_wired_into_four_show_pages(): void
    {
        $pages = [
            'resources/js/Pages/Invoices/Show.vue' => 'invoices',
            'resources/js/Pages/Tickets/Show.vue' => 'tickets',
            'resources/js/Pages/MessageThreads/Show.vue' => 'messages',
            'resources/js/Pages/Tenant/Inbox/Show.vue' => 'messages',
        ];

        foreach ($pages as $path => $family) {
            $contents = file_get_contents(base_path($path));
            $this->assertStringContainsString(
                'PendingSyncBadge',
                $contents,
                "{$path} must import + mount PendingSyncBadge",
            );
            $this->assertStringContainsString(
                "route-family=\"{$family}\"",
                $contents,
                "{$path} must specify routeFamily='{$family}'",
            );
        }
    }

    public function test_ticket_update_asserts_if_match_header(): void
    {
        $contents = file_get_contents(base_path('app/Http/Controllers/TicketController.php'));

        $this->assertStringContainsString('$ticket->assertIfMatch(', $contents);
        $this->assertStringContainsString("\$request->header('If-Match')", $contents);
    }

    public function test_water_reading_update_asserts_if_match_header(): void
    {
        $contents = file_get_contents(base_path('app/Http/Controllers/WaterReadingController.php'));

        $this->assertStringContainsString('$reading->assertIfMatch(', $contents);
        $this->assertStringContainsString("\$request->header('If-Match')", $contents);
    }

    public function test_assert_if_match_throws_on_stale_version(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $ticket = new Ticket;
        $ticket->forceFill([
            'landlord_id' => $landlord->id,
            'version' => 5,
        ]);

        // Stale incoming version triggers the exception.
        $this->expectException(WriteConflictException::class);
        $ticket->assertIfMatch(4, ['title' => 'updated']);
    }

    public function test_assert_if_match_passes_on_current_version(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $ticket = new Ticket;
        $ticket->forceFill([
            'landlord_id' => $landlord->id,
            'version' => 5,
        ]);

        // Current version matches — no exception.
        $ticket->assertIfMatch(5, ['title' => 'updated']);
        $this->addToAssertionCount(1);
    }

    public function test_assert_if_match_skips_when_header_null(): void
    {
        $ticket = new Ticket;
        $ticket->forceFill([
            'landlord_id' => 1,
            'version' => 5,
        ]);

        // Null header = backward-compat skip per Phase 62 contract.
        $ticket->assertIfMatch(null, ['title' => 'whatever']);
        $this->addToAssertionCount(1);
    }
}
