<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Building;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-54 COST-UI-1/2/3 watchdog.
 */
class Phase54CostUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_show_page_includes_costs_prop(): void
    {
        [$landlord, $ticket] = $this->makeFixture();
        TicketCost::create([
            'ticket_id' => $ticket->id,
            'category' => TicketCost::CATEGORY_VENDOR,
            'amount_cents' => 250000,
            'currency' => 'KES',
            'recorded_at' => now(),
        ]);

        $this->actingAs($landlord)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('costs')
                ->where('costs.vendor', 250000)
                ->where('costs.total', 250000)
                ->where('canManageCosts', true));
    }

    public function test_tenant_show_page_omits_costs(): void
    {
        [$landlord, $ticket] = $this->makeFixture();
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        $ticket->reporter_id = $tenant->id;
        $ticket->save();

        $this->actingAs($tenant)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('costs', null)
                ->where('canManageCosts', false));
    }

    public function test_landlord_can_record_a_vendor_cost(): void
    {
        [$landlord, $ticket] = $this->makeFixture();

        $this->actingAs($landlord)
            ->post(route('tickets.costs.store', $ticket), [
                'category' => 'vendor',
                'amount_cents' => 175000,
                'notes' => 'Plumber callout',
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_costs', [
            'ticket_id' => $ticket->id,
            'category' => 'vendor',
            'amount_cents' => 175000,
            'currency' => 'KES',
            'notes' => 'Plumber callout',
            'recorded_by' => $landlord->id,
        ]);

        $this->assertSame(
            1,
            TicketActivity::query()
                ->where('ticket_id', $ticket->id)
                ->where('action', 'cost_recorded')
                ->count(),
            'TicketActivity row must record the audit entry.',
        );
    }

    public function test_parts_category_rejected_at_validation(): void
    {
        [$landlord, $ticket] = $this->makeFixture();

        $this->actingAs($landlord)
            ->post(route('tickets.costs.store', $ticket), [
                'category' => 'parts',
                'amount_cents' => 1000,
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_caretaker_cannot_record_cost(): void
    {
        [$landlord, $ticket] = $this->makeFixture();
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $this->actingAs($caretaker)
            ->post(route('tickets.costs.store', $ticket), [
                'category' => 'vendor',
                'amount_cents' => 1000,
            ])
            ->assertForbidden();
    }

    public function test_landlord_cannot_record_cost_against_another_landlords_ticket(): void
    {
        [$landlordA, $ticketA] = $this->makeFixture();
        $landlordB = User::factory()->create(['role' => 'landlord']);

        // Route model binding may resolve the ticket without enforcing
        // TenantScope; the policy then denies. Either 403 (policy) or
        // 404 (scope) is acceptable — both prove no cross-tenant leak.
        $response = $this->actingAs($landlordB)
            ->post(route('tickets.costs.store', $ticketA), [
                'category' => 'vendor',
                'amount_cents' => 1000,
            ]);
        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * @return array{User, Ticket}
     */
    private function makeFixture(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create([
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
        ]);
        $ticket = Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'medium',
            'status' => 'open',
            'title' => 'Test ticket',
            'description' => 'Test',
        ]);

        return [$landlord, $ticket];
    }
}
