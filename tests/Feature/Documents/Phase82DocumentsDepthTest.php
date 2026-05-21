<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Events\DocumentExpiryApproaching;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-82 DOCUMENTS-DEPTH: lifecycle scopes, renewal, expiry reminders,
 * landlord surface, and notice generation.
 */
class Phase82DocumentsDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    private function doc(array $attrs = []): Document
    {
        return Model::withoutEvents(fn () => Document::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => Lease::class,
            'documentable_id' => $this->lease->id,
            'document_type' => 'insurance',
            'is_renewable' => true,
            'expires_at' => now()->addDays(10),
        ], $attrs)));
    }

    public function test_scope_current_excludes_superseded(): void
    {
        $old = $this->doc();
        $new = $this->doc();
        $old->update(['superseded_by_document_id' => $new->id]);

        $ids = Document::query()->current()->pluck('id')->all();
        $this->assertContains($new->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_due_for_reminder_filters(): void
    {
        $due = $this->doc(['expires_at' => now()->addDays(10)]);
        $this->doc(['is_renewable' => false, 'expires_at' => now()->addDays(5)]);   // not renewable
        $this->doc(['expires_at' => now()->addDays(200)]);                          // outside window
        $perDoc = $this->doc(['reminder_days' => 90, 'expires_at' => now()->addDays(60)]); // own window

        $ids = Document::query()->dueForReminder(30)->pluck('id')->all();
        $this->assertContains($due->id, $ids);
        $this->assertContains($perDoc->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_expiry_status(): void
    {
        $this->assertSame('expired', $this->doc(['expires_at' => now()->subDay()])->expiryStatus());
        $this->assertSame('expiring_soon', $this->doc(['expires_at' => now()->addDays(5)])->expiryStatus());
        $this->assertSame('valid', $this->doc(['expires_at' => now()->addDays(200)])->expiryStatus());
        $this->assertSame('none', $this->doc(['expires_at' => null])->expiryStatus());
    }

    public function test_renew_supersedes_and_creates_fresh(): void
    {
        $old = $this->doc(['expires_at' => now()->addDays(3)]);

        $this->actingAs($this->landlord)
            ->post(route('documents.renew', $old->id), [
                'file' => UploadedFile::fake()->createWithContent('renewal.pdf', "%PDF-1.4\nrenewed\n%%EOF\n"),
                'expires_at' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $old->refresh();
        $this->assertNotNull($old->superseded_by_document_id);
        $this->assertNotContains($old->id, Document::query()->current()->pluck('id')->all());
    }

    public function test_scan_expiring_fires_event_and_is_idempotent(): void
    {
        Event::fake([DocumentExpiryApproaching::class]);
        $this->doc(['expires_at' => now()->addDays(5)]);
        $this->doc(['is_renewable' => false, 'expires_at' => now()->addDays(5)]);

        $this->artisan('documents:scan-expiring')->assertExitCode(0);
        Event::assertDispatchedTimes(DocumentExpiryApproaching::class, 1);

        // Second run same month → idempotent (cache lock).
        $this->artisan('documents:scan-expiring')->assertExitCode(0);
        Event::assertDispatchedTimes(DocumentExpiryApproaching::class, 1);
    }

    public function test_reminder_listener_notifies_landlord(): void
    {
        $doc = $this->doc(['expires_at' => now()->addDays(5)]);

        app(\App\Listeners\NotifyOnDocumentExpiry::class)->handle(new DocumentExpiryApproaching($doc, 5));

        $this->assertTrue(
            Notification::where('recipient_id', $this->landlord->id)
                ->where('type', Notification::TYPE_DOCUMENT_EXPIRY)->exists(),
        );
    }

    public function test_dashboard_counts_expiring_documents(): void
    {
        $this->doc(['expires_at' => now()->addDays(10)]);
        $this->doc(['expires_at' => now()->addDays(200)]); // not within 30

        $data = app(DashboardService::class)->getLandlordDashboardData($this->landlord->fresh(), new Request);

        $this->assertSame(1, $data['actionItems']['expiring_documents']);
    }

    public function test_rollup_command_exits_zero(): void
    {
        $this->doc(['expires_at' => now()->addDays(10)]);
        $this->artisan('documents:expiry-rollup')->assertExitCode(0);
    }

    public function test_generate_notice_creates_document_on_lease(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('documents.generate-notice', $this->lease->id), [
                'notice_type' => 'rent_increase',
                'reason' => 'Annual increase of 5%.',
                'effective_date' => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertTrue(
            Document::where('documentable_type', Lease::class)
                ->where('documentable_id', $this->lease->id)
                ->where('document_type', 'notice')->exists(),
        );
    }
}
