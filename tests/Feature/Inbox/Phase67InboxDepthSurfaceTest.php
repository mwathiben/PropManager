<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Console\Commands\InboxDepthRollup;
use App\Events\MessageRead;
use App\Http\Controllers\MessageThreadReadAllController;
use App\Http\Controllers\MessageThreadSearchController;
use App\Http\Controllers\Tenant\InboxSearchController;
use App\Services\Inbox\MessageSearchService;
use App\Services\Inbox\Scanning\AttachmentScannerFactory;
use App\Services\Inbox\Scanning\AttachmentScannerInterface;
use App\Services\Inbox\Scanning\ClamavScanner;
use App\Services\Inbox\Scanning\FakeScanner;
use App\Services\Inbox\Scanning\NullScanner;
use App\Services\Inbox\Scanning\ScanResult;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-67 INBOX-DEPTH CI (CI-1): cross-category surface map. Guards the
 * shipped surface of all five feature sub-phases (READ-RECEIPTS, PRESENCE,
 * MESSAGE-SEARCH, ATTACHMENT-SCAN, INBOX-OBSERVABILITY) against drift —
 * classes, columns, routes, channels, Vue tokens, lang, command, alert.
 *
 * NOTE: MESSAGE-SEARCH ships a participant-scoped LIKE matcher, NOT the
 * FULLTEXT index the PRD originally proposed — MySQL FULLTEXT cannot see
 * uncommitted rows inside a RefreshDatabase transaction, which made the
 * isolation tests untestable. The deviation is recorded in the audit
 * closeout; this surface test asserts the service, not a FULLTEXT index.
 */
class Phase67InboxDepthSurfaceTest extends TestCase
{
    private function vue(string $relative): string
    {
        $path = base_path('resources/js/'.$relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_read_receipts_surface(): void
    {
        $this->assertTrue(class_exists(MessageRead::class));
        $this->assertTrue(class_exists(MessageThreadReadAllController::class));
        $this->assertTrue(Route::has('message-threads.read-all'));
        $this->assertTrue(Route::has('tenant.inbox.read-all'));
        $this->assertTrue(Schema::hasColumn('message_thread_participants', 'last_read_at'));

        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $src = $this->vue($page);
            $this->assertStringContainsString('.message.read', $src);
        }

        // Phase-71 BUBBLES extracted the seen tick into the shared MessageBubble.
        $this->assertStringContainsString(
            'data-testid="message-seen"',
            $this->vue('Components/Inbox/MessageBubble.vue'),
        );

        $this->assertIsArray(__('inbox.seen'));
    }

    public function test_presence_surface(): void
    {
        $channels = (string) file_get_contents(base_path('routes/channels.php'));
        $this->assertStringContainsString('inbox.presence.{threadId}', $channels);

        $this->vue('composables/usePresenceChannel.ts');

        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $src = $this->vue($page);
            $this->assertStringContainsString('data-testid="presence-online"', $src);
            $this->assertStringContainsString('data-testid="presence-typing"', $src);
            $this->assertStringContainsString('usePresenceChannel', $src);
        }

        $this->assertIsArray(__('inbox.presence'));
    }

    public function test_message_search_surface(): void
    {
        $this->assertTrue(class_exists(MessageSearchService::class));
        $this->assertTrue(class_exists(MessageThreadSearchController::class));
        $this->assertTrue(class_exists(InboxSearchController::class));
        $this->assertTrue(Route::has('message-threads.search'));
        $this->assertTrue(Route::has('tenant.inbox.search'));

        $this->vue('Pages/MessageThreads/Search.vue');
        $this->vue('Pages/Tenant/Inbox/Search.vue');

        foreach (['Pages/MessageThreads/Index.vue', 'Pages/Tenant/Inbox/Index.vue'] as $page) {
            $this->assertStringContainsString('data-testid="inbox-search"', $this->vue($page));
        }

        $this->assertIsArray(__('inbox.search'));
    }

    public function test_attachment_scan_surface(): void
    {
        $this->assertTrue(interface_exists(AttachmentScannerInterface::class));
        foreach ([ClamavScanner::class, NullScanner::class, FakeScanner::class, AttachmentScannerFactory::class, ScanResult::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} must exist");
        }

        $this->assertTrue(Schema::hasColumn('documents', 'scan_status'));
        $this->assertIsString(config('inbox.scan.driver'));
        $this->assertContains(config('inbox.scan.fail_closed'), [true, false]);

        $hint = $this->vue('Components/Inbox/AttachmentPreviewList.vue');
        $this->assertStringContainsString('data-testid="attachment-scan-hint"', $hint);

        foreach (['Pages/MessageThreads/Show.vue', 'Pages/Tenant/Inbox/Show.vue'] as $page) {
            $this->assertStringContainsString('data-testid="attachment-blocked"', $this->vue($page));
        }

        $this->assertIsArray(__('inbox.scan'));
    }

    public function test_observability_surface(): void
    {
        $this->assertTrue(class_exists(InboxDepthRollup::class));
        $this->assertArrayHasKey('inbox:depth-rollup', Artisan::all());

        $alert = collect(config('alerts.alerts'))->firstWhere('key', 'inbox_attachment_infected');
        $this->assertNotNull($alert);
        $this->assertSame('sev2', $alert['severity']);
    }
}
