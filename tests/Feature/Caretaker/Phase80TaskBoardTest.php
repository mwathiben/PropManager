<?php

declare(strict_types=1);

namespace Tests\Feature\Caretaker;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-80 TASK-BOARD: the caretaker's mobile-first daily board + inline,
 * assignee-only forward status transitions.
 */
class Phase80TaskBoardTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $caretaker;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

    public function test_board_shows_only_own_open_assigned_tasks(): void
    {
        $mine = $this->ticket();
        $other = $this->createCaretakerForLandlord($this->landlord);
        $theirs = $this->ticket(['assigned_to' => $other->id]);

        $response = $this->actingAs($this->caretaker)->get(route('tasks.index'));
        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['tasks'])->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_assignee_can_transition_forward(): void
    {
        $ticket = $this->ticket(['status' => 'open']);

        $this->actingAs($this->caretaker)
            ->post(route('tasks.transition', $ticket->id), ['status' => 'acknowledged'])
            ->assertRedirect();

        $this->assertSame('acknowledged', $ticket->fresh()->status->value);
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'action' => TicketActivity::ACTION_STATUS_CHANGED,
        ]);
    }

    public function test_resolve_transition_stamps_resolved_at(): void
    {
        $ticket = $this->ticket(['status' => 'in_progress']);

        $this->actingAs($this->caretaker)
            ->post(route('tasks.transition', $ticket->id), ['status' => 'resolved', 'notes' => 'fixed'])
            ->assertRedirect();

        $fresh = $ticket->fresh();
        $this->assertSame('resolved', $fresh->status->value);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_non_assignee_cannot_transition(): void
    {
        $other = $this->createCaretakerForLandlord($this->landlord);
        $ticket = $this->ticket();

        $this->actingAs($other)
            ->post(route('tasks.transition', $ticket->id), ['status' => 'acknowledged'])
            ->assertForbidden();
    }

    public function test_backward_transition_rejected(): void
    {
        $ticket = $this->ticket(['status' => 'in_progress']);

        $this->actingAs($this->caretaker)
            ->post(route('tasks.transition', $ticket->id), ['status' => 'acknowledged'])
            ->assertSessionHasErrors('status');

        $this->assertSame('in_progress', $ticket->fresh()->status->value);
    }

    public function test_water_cta_reflects_module_state(): void
    {
        $ticket = $this->ticket();

        $response = $this->actingAs($this->caretaker)->get(route('tasks.index'));
        // No water billing configured for this landlord → CTA hidden.
        $this->assertFalse($response->viewData('page')['props']['waterEnabled']);
    }
}
