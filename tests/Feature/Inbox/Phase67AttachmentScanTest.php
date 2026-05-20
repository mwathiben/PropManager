<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Models\Document;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Services\Inbox\MessageAttachmentService;
use App\Services\Inbox\Scanning\AttachmentScannerInterface;
use App\Services\Inbox\Scanning\FakeScanner;
use App\Services\Inbox\Scanning\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase-67 ATTACHMENT-SCAN CI: every inbox attachment is virus-scanned
 * before a byte is persisted. Infected files abort the whole batch (no
 * Document row, nothing on disk); a scanner *error* is governed by the
 * fail_closed policy (reject by default, persist with scan_status=error
 * when fail-open). The infection audit is durable across the request
 * transaction's rollback.
 */
class Phase67AttachmentScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['inbox.scan.driver' => 'fake']);
    }

    /**
     * @return array{0: MessageThread, 1: User, 2: User} [thread, landlord, tenant]
     */
    private function makeThread(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);

        $thread = MessageThread::create(['landlord_id' => $landlord->id, 'title' => 'Maintenance']);
        $thread->participants()->attach($landlord->id, ['role' => 'landlord']);
        $thread->participants()->attach($tenant->id, ['role' => 'tenant']);

        return [$thread, $landlord, $tenant];
    }

    private function service(): MessageAttachmentService
    {
        return app(MessageAttachmentService::class);
    }

    private function eicarFile(string $name = 'virus.txt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            'header '.FakeScanner::EICAR_MARKER.' footer',
        );
    }

    /**
     * An EICAR-carrying file that also passes the StoreMessageRequest
     * mimes:pdf rule (real %PDF magic bytes), so the endpoint reaches the
     * scan gate rather than being rejected by upload validation.
     */
    private function eicarPdf(string $name = 'invoice.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n% ".FakeScanner::EICAR_MARKER."\n%%EOF\n",
        );
    }

    private function bindErrorScanner(): void
    {
        $this->app->instance(AttachmentScannerInterface::class, new class implements AttachmentScannerInterface
        {
            public function scan(string $absolutePath): ScanResult
            {
                return ScanResult::error('clamd unreachable');
            }
        });
    }

    public function test_clean_file_persists_with_clean_status(): void
    {
        [$thread, , $tenant] = $this->makeThread();
        $message = $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'see attached']);
        $file = UploadedFile::fake()->create('report.pdf', 12, 'application/pdf');

        $scanned = $this->service()->scan([$file], $tenant, $thread->id);
        $documents = $this->service()->persist($message, $scanned);

        $this->assertCount(1, $documents);
        $this->assertSame(Document::SCAN_CLEAN, $documents[0]->scan_status);
        $this->assertDatabaseHas('documents', [
            'documentable_id' => $message->id,
            'documentable_type' => Message::class,
            'scan_status' => Document::SCAN_CLEAN,
        ]);
        Storage::disk('local')->assertExists($documents[0]->file_path);
        $this->assertSame(Message::TYPE_ATTACHMENT, $message->fresh()->message_type);
    }

    public function test_infected_file_is_rejected_and_nothing_persists(): void
    {
        [$thread, , $tenant] = $this->makeThread();

        try {
            $this->service()->scan([$this->eicarFile()], $tenant, $thread->id);
            $this->fail('Expected ValidationException for infected attachment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attachments', $e->errors());
            $this->assertSame(__('inbox.scan.blocked'), $e->errors()['attachments'][0]);
        }

        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_infected_member_aborts_whole_batch_atomically(): void
    {
        [$thread, , $tenant] = $this->makeThread();
        $clean = UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf');
        $bad = $this->eicarFile();

        try {
            $this->service()->scan([$clean, $bad], $tenant, $thread->id);
            $this->fail('Expected ValidationException aborting the batch.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_infected_upload_audits_against_sender(): void
    {
        [$thread, , $tenant] = $this->makeThread();

        try {
            $this->service()->scan([$this->eicarFile('payload.exe')], $tenant, $thread->id);
        } catch (ValidationException) {
            // expected
        }

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'inbox.attachment.infected',
            'auditable_id' => $tenant->id,
            'auditable_type' => User::class,
        ]);
    }

    public function test_infected_upload_via_endpoint_rejects_and_keeps_audit_despite_rollback(): void
    {
        [$thread, $landlord] = $this->makeThread();

        $response = $this->actingAs($landlord)->post(
            route('message-threads.messages.store', $thread->id),
            [
                'body' => 'see the attached invoice',
                'attachments' => [$this->eicarPdf()],
            ],
        );

        $response->assertSessionHasErrors('attachments');

        // The message row was created inside the request transaction; the
        // scan runs BEFORE that transaction opens, so its rejection leaves
        // no message and no document behind...
        $this->assertSame(0, $thread->messages()->count());
        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());

        // ...but the forensic audit row survives — it was never inside the
        // doomed transaction (this is the regression the split guards).
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'inbox.attachment.infected',
            'auditable_id' => $landlord->id,
            'auditable_type' => User::class,
        ]);
    }

    public function test_fail_closed_rejects_when_scanner_errors(): void
    {
        config(['inbox.scan.fail_closed' => true]);
        $this->bindErrorScanner();
        [$thread, , $tenant] = $this->makeThread();
        $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

        try {
            $this->service()->scan([$file], $tenant, $thread->id);
            $this->fail('Expected ValidationException under fail-closed policy.');
        } catch (ValidationException $e) {
            $this->assertSame(__('inbox.scan.unavailable'), $e->errors()['attachments'][0]);
        }

        $this->assertSame(0, Document::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_fail_open_persists_with_error_status_when_scanner_errors(): void
    {
        config(['inbox.scan.fail_closed' => false]);
        $this->bindErrorScanner();
        [$thread, , $tenant] = $this->makeThread();
        $message = $thread->messages()->create(['sender_id' => $tenant->id, 'body' => 'see attached']);
        $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

        $scanned = $this->service()->scan([$file], $tenant, $thread->id);
        $documents = $this->service()->persist($message, $scanned);

        $this->assertCount(1, $documents);
        $this->assertSame(Document::SCAN_ERROR, $documents[0]->scan_status);
        $this->assertDatabaseHas('documents', [
            'documentable_id' => $message->id,
            'scan_status' => Document::SCAN_ERROR,
        ]);
        Storage::disk('local')->assertExists($documents[0]->file_path);
    }
}
