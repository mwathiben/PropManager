<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Models\User;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-65 MORPH-EXPAND watchdog: the allow-list guard +
 * HasLegalHolds trait coverage + LegalHoldPolicy cross-tenant gate.
 */
class Phase65MorphExpandTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $a = $this->createLandlordWithFullSetup();
        $this->landlord = $a['landlord'];

        $b = $this->createLandlordWithFullSetup();
        $this->otherLandlord = $b['landlord'];
    }

    public function test_allowed_holdable_types_lists_four_subjects(): void
    {
        $this->assertSame(
            [MessageThread::class, Document::class, Invoice::class, Ticket::class],
            LegalHoldRegistry::ALLOWED_HOLDABLE_TYPES,
        );
    }

    public function test_hold_rejects_subject_outside_allow_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('legal_hold.unsupported_holdable_type');

        LegalHoldRegistry::hold($this->landlord, $this->landlord, 'reason');
    }

    public function test_hold_succeeds_for_every_allowed_subject(): void
    {
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);
        $thread->participants()->attach($this->landlord->id, ['role' => MessageThread::ROLE_LANDLORD]);

        $document = Document::factory()->forUser($this->landlord)->create(['landlord_id' => $this->landlord->id]);
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $ticket = Ticket::factory()->forLandlord($this->landlord)->reportedBy($this->landlord)->create();

        foreach ([$thread, $document, $invoice, $ticket] as $subject) {
            $hold = LegalHoldRegistry::hold($subject, $this->landlord, 'preservation order');
            $this->assertInstanceOf(LegalHold::class, $hold);
            $this->assertSame($subject::class, $hold->holdable_type);
            $this->assertSame((int) $subject->getKey(), (int) $hold->holdable_id);
        }
    }

    public function test_has_legal_holds_trait_exposes_relation_and_is_held(): void
    {
        $document = Document::factory()->forUser($this->landlord)->create(['landlord_id' => $this->landlord->id]);

        $this->assertFalse($document->isHeld());
        $this->assertTrue($document->isHoldable());

        LegalHoldRegistry::hold($document, $this->landlord, 'reason');

        $this->assertTrue($document->fresh()->isHeld());
        $this->assertCount(1, $document->fresh()->legalHolds);
    }

    public function test_ticket_uses_auditable_and_has_legal_holds_traits(): void
    {
        $ticket = Ticket::factory()->forLandlord($this->landlord)->reportedBy($this->landlord)->create();

        $this->assertTrue(method_exists($ticket, 'getLawfulBasis'), 'Auditable must add getLawfulBasis to Ticket');
        $this->assertTrue(method_exists($ticket, 'isHeld'), 'HasLegalHolds must add isHeld to Ticket');
        $this->assertTrue(method_exists($ticket, 'legalHolds'), 'HasLegalHolds must add legalHolds to Ticket');
    }

    public function test_policy_allows_own_subject_create(): void
    {
        $invoice = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);

        $this->assertTrue(
            $this->landlord->can('create', [LegalHold::class, Invoice::class, $invoice->id]),
        );
    }

    public function test_policy_rejects_cross_landlord_hold(): void
    {
        $otherInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        $this->assertFalse(
            $this->landlord->can('create', [LegalHold::class, Invoice::class, $otherInvoice->id]),
        );
    }

    public function test_policy_rejects_disallowed_subject_class(): void
    {
        $this->assertFalse(
            $this->landlord->can('create', [LegalHold::class, User::class, $this->landlord->id]),
        );
    }

    public function test_release_policy_rejects_cross_landlord(): void
    {
        $otherInvoice = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);
        $hold = LegalHoldRegistry::hold($otherInvoice, $this->otherLandlord, 'reason');

        $this->assertFalse($this->landlord->can('release', $hold));
        $this->assertTrue($this->otherLandlord->can('release', $hold));
    }
}
