<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Document;
use App\Models\Lease;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-71 MEDIA-CI (M-1): the participant-gated message-attachment endpoint.
 * Authorisation is by thread participation (NOT DocumentPolicy, which denies
 * tenants Message-attached documents), the document must belong to the named
 * message-in-thread, and only clean, existing files are served.
 */
class Phase71AttachmentTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenantA;

    private User $tenantB;

    private Lease $leaseA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];

        ['tenant' => $this->tenantA, 'lease' => $this->leaseA] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
        ['tenant' => $this->tenantB] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->get(1),
        );
    }

    private function threadWith(User ...$participants): MessageThread
    {
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);
        foreach ($participants as $p) {
            $thread->participants()->attach($p->id, [
                'role' => $p->is($this->landlord) ? MessageThread::ROLE_LANDLORD : MessageThread::ROLE_TENANT,
            ]);
        }

        return $thread;
    }

    private function attachmentOn(Message $message, string $scanStatus = 'clean', bool $putFile = true): Document
    {
        $doc = Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => Message::class,
            'documentable_id' => $message->id,
            'mime_type' => 'image/jpeg',
            'scan_status' => $scanStatus,
        ]);

        if ($putFile) {
            Storage::disk('local')->put($doc->file_path, 'fake-bytes');
        }

        return $doc;
    }

    public function test_participant_can_fetch_a_clean_attachment(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'See photo.']);
        $doc = $this->attachmentOn($message);

        $this->actingAs($this->tenantA)
            ->get(route('tenant.inbox.attachments.show', [$thread->id, $message->id, $doc->id]))
            ->assertRedirect();
    }

    public function test_non_participant_is_forbidden(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'Private.']);
        $doc = $this->attachmentOn($message);

        $this->actingAs($this->tenantB)
            ->get(route('message-threads.attachments.show', [$thread->id, $message->id, $doc->id]))
            ->assertForbidden();
    }

    public function test_document_from_another_thread_is_rejected(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'Here.']);

        $otherThread = $this->threadWith($this->landlord, $this->tenantA);
        $otherMessage = $otherThread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'Other.']);
        $foreignDoc = $this->attachmentOn($otherMessage);

        $this->actingAs($this->landlord)
            ->get(route('message-threads.attachments.show', [$thread->id, $message->id, $foreignDoc->id]))
            ->assertNotFound();
    }

    public function test_non_clean_attachment_is_not_served(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'Sketchy.']);
        $doc = $this->attachmentOn($message, scanStatus: 'error');

        $this->actingAs($this->landlord)
            ->get(route('message-threads.attachments.show', [$thread->id, $message->id, $doc->id]))
            ->assertNotFound();
    }

    public function test_participant_passes_authorization_even_when_file_is_missing(): void
    {
        $thread = $this->threadWith($this->landlord, $this->tenantA);
        $message = $thread->messages()->create(['sender_id' => $this->landlord->id, 'body' => 'Gone.']);
        $doc = $this->attachmentOn($message, putFile: false);

        // 404 (file missing) — NOT 403 — proves the participant cleared auth.
        $this->actingAs($this->tenantA)
            ->get(route('tenant.inbox.attachments.show', [$thread->id, $message->id, $doc->id]))
            ->assertNotFound();
    }
}
