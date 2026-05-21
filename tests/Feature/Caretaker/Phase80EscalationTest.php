<?php

declare(strict_types=1);

namespace Tests\Feature\Caretaker;

use App\Events\TicketEscalated;
use App\Events\TicketSlaBreached;
use App\Listeners\AutoEscalateOnSlaBreach;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\Maintenance\TicketEscalationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-80 ESCALATION: caretaker→landlord escalation routing.
 */
class Phase80EscalationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $caretaker;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->caretaker = $this->createCaretakerForLandlord($this->landlord, $this->building);
    }

    private function ticket(array $attrs = []): Ticket
    {
        return Model::withoutEvents(fn () => Ticket::factory()->open()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'assigned_to' => $this->caretaker->id,
        ], $attrs)));
    }

    public function test_caretaker_escalates_assigned_ticket(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->caretaker)
            ->post(route('tasks.escalate', $ticket->id), ['reason' => 'Cannot access the unit'])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->isEscalated());
        $this->assertSame($this->caretaker->id, $fresh->escalated_by);
        $this->assertStringContainsString('Cannot access', $fresh->escalation_reason);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => TicketActivity::ACTION_ESCALATED,
        ]);
    }

    public function test_escalate_dispatches_event(): void
    {
        Event::fake([TicketEscalated::class]);
        $ticket = $this->ticket();

        app(TicketEscalationService::class)->escalate($ticket, $this->caretaker, 'blocked');

        Event::assertDispatched(TicketEscalated::class);
    }

    public function test_non_assignee_caretaker_cannot_escalate(): void
    {
        $other = $this->createCaretakerForLandlord($this->landlord);
        $ticket = $this->ticket();

        $this->actingAs($other)
            ->post(route('tasks.escalate', $ticket->id), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertFalse($ticket->fresh()->isEscalated());
    }

    public function test_escalate_requires_a_reason(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->caretaker)
            ->post(route('tasks.escalate', $ticket->id), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_double_escalate_is_a_noop(): void
    {
        $ticket = $this->ticket();
        $service = app(TicketEscalationService::class);

        $first = $service->escalate($ticket, $this->caretaker, 'first');
        $service->escalate($first, $this->caretaker, 'second');

        $this->assertSame('first', $ticket->fresh()->escalation_reason);
        $this->assertSame(1, TicketActivity::where('ticket_id', $ticket->id)
            ->where('action', TicketActivity::ACTION_ESCALATED)->count());
    }

    public function test_terminal_ticket_cannot_be_escalated(): void
    {
        $ticket = $this->ticket(['status' => 'resolved', 'resolved_at' => now()]);

        $this->expectException(ValidationException::class);
        app(TicketEscalationService::class)->escalate($ticket, $this->caretaker, 'too late');
    }

    public function test_landlord_acknowledges_escalation(): void
    {
        $ticket = $this->ticket();
        app(TicketEscalationService::class)->escalate($ticket, $this->caretaker, 'blocked');

        $this->actingAs($this->landlord)
            ->post(route('tickets.escalation.acknowledge', $ticket->id))
            ->assertRedirect();

        $this->assertFalse($ticket->fresh()->isEscalated());
        $this->assertSame(0, Ticket::query()->escalated()->where('id', $ticket->id)->count());
    }

    public function test_reassign_clears_open_escalation(): void
    {
        $ticket = $this->ticket();
        app(TicketEscalationService::class)->escalate($ticket, $this->caretaker, 'blocked');
        $other = $this->createCaretakerForLandlord($this->landlord);

        $this->actingAs($this->landlord)
            ->post(route('tickets.assign', $ticket->id), ['assigned_to' => $other->id])
            ->assertRedirect();

        $this->assertFalse($ticket->fresh()->isEscalated());
    }

    public function test_scope_escalated_filters_open_only(): void
    {
        $open = $this->ticket();
        $acked = $this->ticket();
        $service = app(TicketEscalationService::class);
        $service->escalate($open, $this->caretaker, 'open one');
        $service->escalate($acked, $this->caretaker, 'will ack');
        $service->acknowledge($acked, $this->landlord);

        $ids = Ticket::query()->escalated()->pluck('id')->all();
        $this->assertContains($open->id, $ids);
        $this->assertNotContains($acked->id, $ids);
    }

    public function test_auto_escalation_on_sla_breach_respects_flag(): void
    {
        config()->set('maintenance.auto_escalate_on_sla_breach', false);
        $ticket = $this->ticket(['resolution_due_at' => now()->subDay()]);
        app(AutoEscalateOnSlaBreach::class)->handle(
            new TicketSlaBreached($ticket, CarbonImmutable::now(), TicketSlaBreached::TYPE_RESOLUTION),
        );
        $this->assertFalse($ticket->fresh()->isEscalated(), 'flag off → no auto-escalation');

        config()->set('maintenance.auto_escalate_on_sla_breach', true);
        app(AutoEscalateOnSlaBreach::class)->handle(
            new TicketSlaBreached($ticket->fresh(), CarbonImmutable::now(), TicketSlaBreached::TYPE_RESOLUTION),
        );
        $this->assertTrue($ticket->fresh()->isEscalated(), 'flag on → auto-escalated');
    }
}
