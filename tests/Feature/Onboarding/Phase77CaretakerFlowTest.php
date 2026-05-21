<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Onboarding\OnboardingFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-77 CARETAKER-FLOW: the caretaker onboarding flow is now 5 steps
 * (welcome + orientation bookends) and completion deep-links to the first task.
 */
class Phase77CaretakerFlowTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Building $building;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
            'email_verified_at' => now(),
        ]);
        CaretakerAssignment::create([
            'caretaker_id' => $this->caretaker->id,
            'building_id' => $this->building->id,
            'status' => CaretakerAssignment::STATUS_PENDING,
            'assigned_at' => now(),
        ]);
    }

    public function test_caretaker_flow_has_five_steps(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], OnboardingFlow::forRole('caretaker')->allSteps());
    }

    public function test_assignment_step_props_include_building_stats(): void
    {
        $response = $this->actingAs($this->caretaker)->get(route('onboarding.step', ['step' => 3]));
        $response->assertOk();

        $assignments = $response->viewData('page')['props']['pendingAssignments'];
        $this->assertNotEmpty($assignments);
        $this->assertArrayHasKey('unit_count', $assignments[0]);
        $this->assertArrayHasKey('open_ticket_count', $assignments[0]);
    }

    public function test_orientation_step_props_include_summary_and_first_task(): void
    {
        CaretakerAssignment::where('caretaker_id', $this->caretaker->id)
            ->update(['status' => CaretakerAssignment::STATUS_ACCEPTED]);

        $response = $this->actingAs($this->caretaker)->get(route('onboarding.step', ['step' => 5]));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('buildingSummary', $props);
        $this->assertArrayHasKey('firstTaskUrl', $props);
    }

    public function test_caretaker_walks_all_five_steps_and_lands_on_first_task(): void
    {
        $ticket = Model::withoutEvents(fn () => Ticket::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Leak',
            'description' => 'X',
            'priority' => 'high',
            'status' => 'open',
        ]));

        $this->actingAs($this->caretaker);

        $this->post(route('onboarding.step.save', ['step' => 1]))->assertRedirect(route('onboarding.step', ['step' => 2]));
        $this->post(route('onboarding.step.save', ['step' => 2]), ['name' => 'Caretaker Joe', 'mobile_number' => '0700000000'])
            ->assertRedirect(route('onboarding.step', ['step' => 3]));
        // Step 3: accept (no decline) the pending assignment.
        $this->post(route('onboarding.step.save', ['step' => 3]), [])->assertRedirect(route('onboarding.step', ['step' => 4]));
        $this->post(route('onboarding.step.save', ['step' => 4]), ['email_enabled' => true])->assertRedirect(route('onboarding.step', ['step' => 5]));
        // Step 5: orientation completes onboarding → deep-link to the first open ticket.
        $this->post(route('onboarding.step.save', ['step' => 5]))->assertRedirect(route('tickets.show', $ticket->id));

        $this->assertSame('Caretaker Joe', $this->caretaker->fresh()->name);
        $this->assertSame(CaretakerAssignment::STATUS_ACCEPTED, CaretakerAssignment::where('caretaker_id', $this->caretaker->id)->first()->status);
    }
}
