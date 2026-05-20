<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Exceptions\LegalHoldActiveException;
use App\Models\DeletionRequest;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DataDeletionService;
use App\Services\MetricsService;
use App\Support\LegalHoldRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-68 HOLD-GUARD: an active legal hold blocks destruction on EVERY
 * path (the HasLegalHolds deleting observer), not just the retention
 * cron. The controller surfaces a friendly error + records the blocked
 * attempt; releasing the hold re-enables deletion.
 */
class Phase68HoldGuardTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function assertHeldCannotDelete(Model $subject, string $table): void
    {
        LegalHoldRegistry::hold($subject, $this->landlord, 'preservation order CV/2026/'.$subject->getKey());

        try {
            $subject->delete();
            $this->fail(class_basename($subject).' under hold should not be deletable.');
        } catch (LegalHoldActiveException $e) {
            $this->assertSame($subject::class, $e->subjectType);
        }

        $this->assertDatabaseHas($table, ['id' => $subject->getKey()]);
    }

    public function test_held_subjects_cannot_be_deleted_across_all_types(): void
    {
        $document = Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $this->landlord->id,
            'uploaded_by' => $this->landlord->id,
        ]);
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $ticket = Ticket::factory()->create([
            'landlord_id' => $this->landlord->id,
            'reporter_id' => $this->landlord->id,
        ]);
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id, 'title' => 'Held thread']);

        $this->assertHeldCannotDelete($document, 'documents');
        $this->assertHeldCannotDelete($invoice, 'invoices');
        $this->assertHeldCannotDelete($ticket, 'tickets');
        $this->assertHeldCannotDelete($thread, 'message_threads');
    }

    public function test_unheld_subject_deletes_normally(): void
    {
        // Invoice soft-deletes — an unheld delete must proceed (not blocked).
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
    }

    public function test_release_re_enables_deletion(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        LegalHoldRegistry::hold($invoice, $this->landlord, 'temporary preservation order');
        LegalHoldRegistry::release($invoice, $this->landlord);

        $invoice->delete();

        $this->assertSoftDeleted($invoice);
    }

    public function test_document_controller_blocks_held_delete_with_friendly_error_and_gauge(): void
    {
        $spy = $this->spy(MetricsService::class);

        $document = Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $this->landlord->id,
            'uploaded_by' => $this->landlord->id,
        ]);
        LegalHoldRegistry::hold($document, $this->landlord, 'preservation order CV/2026/9001');

        $this->actingAs($this->landlord)
            ->delete(route('documents.destroy', $document->id))
            ->assertSessionHasErrors('legal_hold');

        $this->assertDatabaseHas('documents', ['id' => $document->id]);

        $spy->shouldHaveReceived('increment')->withArgs(
            fn (string $name, int $by = 1, array $labels = []) => $name === 'legal_hold_blocked_deletions_count'
                && ($labels['subject_type'] ?? null) === 'Document',
        );
    }

    public function test_erasure_preserves_held_documents_as_carve_out(): void
    {
        $spy = $this->spy(MetricsService::class);

        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $this->landlord->id]);

        $heldDoc = Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $tenant->id,
            'uploaded_by' => $this->landlord->id,
        ]);
        $freeDoc = Document::factory()->create([
            'landlord_id' => $this->landlord->id,
            'documentable_type' => User::class,
            'documentable_id' => $tenant->id,
            'uploaded_by' => $this->landlord->id,
        ]);
        LegalHoldRegistry::hold($heldDoc, $this->landlord, 'preservation order — erasure carve-out');

        $request = DeletionRequest::create([
            'user_id' => $tenant->id,
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_deletion_at' => now(),
        ]);

        app(DataDeletionService::class)->executeDeletion($request);

        // The erasure completes — the held doc is preserved (Art. 17(3)(b)),
        // the unheld doc is erased, and the carve-out is recorded.
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseHas('documents', ['id' => $heldDoc->id]);
        $this->assertDatabaseMissing('documents', ['id' => $freeDoc->id]);

        $spy->shouldHaveReceived('increment')->withArgs(
            fn (string $name, int $by = 1) => $name === 'legal_holds_blocking_erasure' && $by === 1,
        );
    }
}
